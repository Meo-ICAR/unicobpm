# Design Tecnico — Filament BPM Admin Panel

## Panoramica dell'architettura

Il pannello BPM si integra nel pannello Filament esistente (`/admin`) sfruttando il meccanismo di autodiscovery già configurato in `AdminPanelProvider`. Non è necessaria alcuna modifica al provider.

L'architettura segue il pattern standard Filament v5:

```
app/Filament/Resources/
├── ProcessResource.php
├── ProcessResource/
│   ├── Pages/
│   │   ├── ListProcesses.php
│   │   ├── CreateProcess.php
│   │   └── EditProcess.php
│   └── RelationManagers/
│       └── ProcessTasksRelationManager.php
└── ProcessInstanceResource.php
    └── ProcessInstanceResource/
        └── Pages/
            └── ListProcessInstances.php
```

---

## Struttura dei file e directory

```
app/
└── Filament/
    └── Resources/
        ├── ProcessResource.php
        ├── ProcessResource/
        │   ├── Pages/
        │   │   ├── ListProcesses.php
        │   │   ├── CreateProcess.php
        │   │   └── EditProcess.php
        │   └── RelationManagers/
        │       └── ProcessTasksRelationManager.php
        ├── ProcessInstanceResource.php
        └── ProcessInstanceResource/
            └── Pages/
                └── ListProcessInstances.php
```

---

## Dipendenze (già presenti nel progetto)

| Pacchetto | Versione | Utilizzo |
|---|---|---|
| `filament/filament` | `^5.6` | Framework base, Form/Table/Actions |
| `laravel/framework` | `^13.8` | Eloquent, casting, eventi |
| `dragonmantank/cron-expression` | (transitiva via `laravel/framework`) | Calcolo `next_run_at` in `Process::updateNextRunDate()` |

> **Nota**: Non vengono introdotte nuove dipendenze Composer. Il componente `KeyValue` è nativo in Filament v5.

---

## ProcessResource

### Classe principale: `ProcessResource.php`

```php
namespace App\Filament\Resources;

use App\Filament\Resources\ProcessResource\Pages;
use App\Filament\Resources\ProcessResource\RelationManagers\ProcessTasksRelationManager;
use App\Models\Process;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProcessResource extends Resource
{
    protected static ?string $model = Process::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'BPM';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form { ... }
    public static function table(Table $table): Table { ... }
    public static function getRelations(): array { ... }
    public static function getPages(): array { ... }
}
```

### Form schema dettagliato

Il form è organizzato in sezioni logiche tramite `Forms\Components\Section`:

**Sezione "Identificazione"**
- `TextInput::make('code')` — required, unique('processes', 'code'), ignoreSelf (edit)
- `TextInput::make('name')` — required
- `TextInput::make('version')` — numeric, default(1), required
- `Toggle::make('is_active')` — default(true), inline

**Sezione "Descrizione"**
- `Textarea::make('description')` — nullable, columnSpanFull

**Sezione "Target & Trigger"**
- `Select::make('target_model')` — nullable, options da un array di model disponibili (es. `\App\Models\UNICOOAM\Employee::class` etc.)
- `KeyValue::make('trigger_filters')` — nullable, keyLabel('Chiave'), valueLabel('Valore')
- 3 colonne affiancate:
  - `TextInput::make('trigger_field')` — nullable
  - `TextInput::make('trigger_state')` — nullable, placeholder('filled | empty | equals')
  - `TextInput::make('trigger_value')` — nullable

**Sezione "Esclusione"**
- 3 colonne affiancate:
  - `TextInput::make('exclude_field')` — nullable
  - `TextInput::make('exclude_state')` — nullable, placeholder('filled | empty | equals')
  - `TextInput::make('exclude_value')` — nullable

**Sezione "Periodicità"**
- `Toggle::make('is_periodic')` — live() per aggiornamento condizionale
- `TextInput::make('cron_expression')` — visible/required quando `is_periodic` è true, con `->hidden(fn (Forms\Get $get) => ! $get('is_periodic'))` e `->required(fn (Forms\Get $get) => $get('is_periodic'))`

**Sezione "Schedulazione" (sola lettura)**
- `DateTimePicker::make('last_activated_at')` — disabled, nullable
- `DateTimePicker::make('next_run_at')` — disabled, nullable

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Section::make('Identificazione')
            ->columns(3)
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->columnSpan(2),
                Forms\Components\TextInput::make('version')
                    ->numeric()
                    ->required()
                    ->default(1),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->inline(false),
            ]),

        Forms\Components\Section::make('Descrizione')
            ->schema([
                Forms\Components\Textarea::make('description')
                    ->nullable()
                    ->columnSpanFull(),
            ]),

        Forms\Components\Section::make('Target & Trigger')
            ->columns(3)
            ->schema([
                Forms\Components\Select::make('target_model')
                    ->nullable()
                    ->options([
                        \App\Models\UNICOOAM\Employee::class => 'Employee',
                        // aggiungere altri modelli target disponibili
                    ])
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('trigger_filters')
                    ->nullable()
                    ->keyLabel('Chiave')
                    ->valueLabel('Valore')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('trigger_field')->nullable(),
                Forms\Components\TextInput::make('trigger_state')
                    ->nullable()
                    ->placeholder('filled | empty | equals'),
                Forms\Components\TextInput::make('trigger_value')->nullable(),
            ]),

        Forms\Components\Section::make('Esclusione Condizionale')
            ->columns(3)
            ->schema([
                Forms\Components\TextInput::make('exclude_field')->nullable(),
                Forms\Components\TextInput::make('exclude_state')
                    ->nullable()
                    ->placeholder('filled | empty | equals'),
                Forms\Components\TextInput::make('exclude_value')->nullable(),
            ]),

        Forms\Components\Section::make('Periodicità')
            ->columns(2)
            ->schema([
                Forms\Components\Toggle::make('is_periodic')
                    ->live()
                    ->inline(false),
                Forms\Components\TextInput::make('cron_expression')
                    ->nullable()
                    ->hidden(fn (Forms\Get $get) => ! $get('is_periodic'))
                    ->required(fn (Forms\Get $get) => (bool) $get('is_periodic'))
                    ->placeholder('0 1 10 * *')
                    ->helperText('Formato cron standard: minuto ora giorno mese giorno-settimana'),
            ]),

        Forms\Components\Section::make('Schedulazione')
            ->columns(2)
            ->schema([
                Forms\Components\DateTimePicker::make('last_activated_at')
                    ->disabled()
                    ->nullable(),
                Forms\Components\DateTimePicker::make('next_run_at')
                    ->disabled()
                    ->nullable(),
            ]),
    ]);
}
```

### Table schema dettagliato

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('code')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('version')
                ->sortable(),
            Tables\Columns\IconColumn::make('is_active')
                ->boolean()
                ->sortable(),
            Tables\Columns\IconColumn::make('is_periodic')
                ->boolean()
                ->sortable(),
            Tables\Columns\TextColumn::make('next_run_at')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            Tables\Columns\TextColumn::make('updated_at')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Stato')
                ->trueLabel('Solo attivi')
                ->falseLabel('Solo disattivati'),
            Tables\Filters\TernaryFilter::make('is_periodic')
                ->label('Periodicità')
                ->trueLabel('Solo periodici'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ])
        ->defaultSort('code');
}
```

### Azione post-salvataggio: afterSave hook

Le pagine `CreateProcess` e `EditProcess` sovrascrivono `afterCreate`/`afterSave` per invocare `updateNextRunDate()`:

```php
// ProcessResource/Pages/CreateProcess.php
namespace App\Filament\Resources\ProcessResource\Pages;

use App\Filament\Resources\ProcessResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProcess extends CreateRecord
{
    protected static string $resource = ProcessResource::class;

    protected function afterCreate(): void
    {
        $this->record->updateNextRunDate();
    }
}

// ProcessResource/Pages/EditProcess.php
namespace App\Filament\Resources\ProcessResource\Pages;

use App\Filament\Resources\ProcessResource;
use Filament\Resources\Pages\EditRecord;

class EditProcess extends EditRecord
{
    protected static string $resource = ProcessResource::class;

    protected function afterSave(): void
    {
        $this->record->updateNextRunDate();
    }
}
```

### getRelations e getPages

```php
public static function getRelations(): array
{
    return [
        ProcessTasksRelationManager::class,
    ];
}

public static function getPages(): array
{
    return [
        'index'  => Pages\ListProcesses::route('/'),
        'create' => Pages\CreateProcess::route('/create'),
        'edit'   => Pages\EditProcess::route('/{record}/edit'),
    ];
}
```

---

## ProcessTasksRelationManager

### Classe: `ProcessResource/RelationManagers/ProcessTasksRelationManager.php`

```php
namespace App\Filament\Resources\ProcessResource\RelationManagers;

use App\Models\BusinessFunction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProcessTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';
    protected static ?string $title = 'Task del Processo';

    public function form(Form $form): Form { ... }
    public function table(Table $table): Table { ... }
}
```

### Form schema del RelationManager

```php
public function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Section::make('Identificazione Task')
            ->columns(2)
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('ordine')
                    ->numeric()
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('code')->nullable(),
                Forms\Components\Select::make('business_function_id')
                    ->relationship('businessFunction', 'name')
                    ->nullable()
                    ->searchable()
                    ->preload(),
            ]),

        Forms\Components\Textarea::make('description')
            ->nullable()
            ->columnSpanFull(),

        Forms\Components\Section::make('Trigger')
            ->columns(3)
            ->schema([
                Forms\Components\TextInput::make('trigger_field')->nullable(),
                Forms\Components\TextInput::make('trigger_state')
                    ->nullable()
                    ->placeholder('filled | empty | equals'),
                Forms\Components\TextInput::make('trigger_value')->nullable(),
            ]),

        Forms\Components\Section::make('Esclusione')
            ->columns(3)
            ->schema([
                Forms\Components\TextInput::make('exclude_field')->nullable(),
                Forms\Components\TextInput::make('exclude_state')
                    ->nullable()
                    ->placeholder('filled | empty | equals'),
                Forms\Components\TextInput::make('exclude_value')->nullable(),
            ]),

        Forms\Components\Section::make('Solleciti')
            ->columns(2)
            ->schema([
                Forms\Components\Toggle::make('has_reminders')
                    ->live()
                    ->inline(false),
                Forms\Components\TextInput::make('reminder_interval_days')
                    ->numeric()
                    ->default(3)
                    ->hidden(fn (Forms\Get $get) => ! $get('has_reminders')),
                Forms\Components\TextInput::make('max_reminders')
                    ->numeric()
                    ->default(5)
                    ->hidden(fn (Forms\Get $get) => ! $get('has_reminders')),
            ]),

        Forms\Components\Section::make('Escalation')
            ->schema([
                Forms\Components\KeyValue::make('escalation_rules')
                    ->nullable()
                    ->keyLabel('Livello')
                    ->valueLabel('Ore massime attesa')
                    ->columnSpanFull(),
            ]),
    ]);
}
```

### Table schema del RelationManager

```php
public function table(Table $table): Table
{
    return $table
        ->recordTitleAttribute('name')
        ->columns([
            Tables\Columns\TextColumn::make('ordine')
                ->sortable(),
            Tables\Columns\TextColumn::make('name')
                ->searchable(),
            Tables\Columns\TextColumn::make('businessFunction.name')
                ->label('Funzione di Business')
                ->placeholder('—'),
            Tables\Columns\IconColumn::make('has_reminders')
                ->boolean()
                ->label('Solleciti'),
            Tables\Columns\TextColumn::make('reminder_interval_days')
                ->label('Intervallo (gg)')
                ->placeholder('—'),
            Tables\Columns\TextColumn::make('max_reminders')
                ->label('Max Solleciti')
                ->placeholder('—'),
        ])
        ->defaultSort('ordine', 'asc')
        ->headerActions([
            Tables\Actions\CreateAction::make(),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
}
```

> **Nota sul modello `ProcessTask`**: Il modello non ha attualmente la relazione `businessFunction()`. Va aggiunta:
> ```php
> // In App\Models\ProcessTask
> public function businessFunction(): BelongsTo
> {
>     return $this->belongsTo(BusinessFunction::class);
> }
> ```

---

## ProcessInstanceResource

### Classe principale: `ProcessInstanceResource.php`

```php
namespace App\Filament\Resources;

use App\Filament\Resources\ProcessInstanceResource\Pages;
use App\Models\Process;
use App\Models\ProcessInstance;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcessInstanceResource extends Resource
{
    protected static ?string $model = ProcessInstance::class;
    protected static ?string $navigationIcon = 'heroicon-o-play-circle';
    protected static ?string $navigationGroup = 'BPM';
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Istanza di Processo';
    protected static ?string $pluralLabel = 'Istanze di Processo';

    // Nessun form: sola lettura
    public static function form(Form $form): Form
    {
        return $form->schema([]); // non usato
    }

    public static function table(Table $table): Table { ... }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessInstances::route('/'),
        ];
    }

    // Nessuna pagina create/edit
}
```

### Sola lettura: eliminazione di create/edit

La risorsa è in sola lettura per convenzione Filament v5:
- Non viene registrata la route `/create` né `/{record}/edit` in `getPages()`
- Non viene inclusa `CreateAction` negli `headerActions` della tabella
- Non vengono incluse `EditAction` né `DeleteAction` nelle `actions` di riga

```php
// ProcessInstanceResource/Pages/ListProcessInstances.php
namespace App\Filament\Resources\ProcessInstanceResource\Pages;

use App\Filament\Resources\ProcessInstanceResource;
use Filament\Resources\Pages\ListRecords;

class ListProcessInstances extends ListRecords
{
    protected static string $resource = ProcessInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return []; // nessuna azione di creazione
    }
}
```

### Table schema dettagliato

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('id')
                ->label('ID')
                ->sortable(),

            Tables\Columns\TextColumn::make('process.name')
                ->label('Processo')
                ->searchable()
                ->sortable(),

            Tables\Columns\BadgeColumn::make('status')
                ->label('Stato')
                ->colors([
                    'gray'    => 'pending',
                    'info'    => 'in_progress',   // blu in Filament
                    'success' => 'completed',
                    'danger'  => 'rejected',
                    'warning' => 'cancelled',      // arancio
                ]),

            Tables\Columns\TextColumn::make('subject_type')
                ->label('Tipo Soggetto')
                ->toggleable(),

            // Colonna "Ultimo Log" con graceful fallback
            Tables\Columns\TextColumn::make('ultimo_log')
                ->label('Ultimo Log')
                ->getStateUsing(function (ProcessInstance $record): string {
                    // Graceful fallback se il modello ProcessInstanceLog non esiste
                    // o se non ci sono log per questa istanza
                    try {
                        $lastLog = $record->logs()->latest()->first();
                        return $lastLog?->message ?? '';
                    } catch (\Throwable $e) {
                        // ProcessInstanceLog potrebbe non esistere ancora come tabella
                        return '';
                    }
                })
                ->placeholder('—')
                ->wrap()
                ->limit(80),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Creata il')
                ->dateTime('d/m/Y H:i')
                ->sortable(),

            Tables\Columns\TextColumn::make('completed_at')
                ->label('Completata il')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->placeholder('—'),
        ])
        ->defaultSort('created_at', 'desc')
        ->filters([
            Tables\Filters\SelectFilter::make('status')
                ->label('Stato')
                ->options([
                    'pending'     => 'In Attesa',
                    'in_progress' => 'In Lavorazione',
                    'completed'   => 'Completata',
                    'rejected'    => 'Respinta',
                    'cancelled'   => 'Annullata',
                ]),
            Tables\Filters\SelectFilter::make('process_id')
                ->label('Processo')
                ->relationship('process', 'name')
                ->searchable()
                ->preload(),
        ])
        ->actions([])      // sola lettura: nessuna azione di riga
        ->bulkActions([]); // sola lettura: nessuna bulk action
}
```

### Gestione graceful del modello ProcessInstanceLog mancante

Il modello `App\Models\ProcessInstanceLog` è referenziato nella relazione `ProcessInstance::logs()` ma potrebbe non essere ancora definito (né la tabella creata). La strategia di gestione è a più livelli:

1. **Livello Resource (column state)**: Il `getStateUsing()` sulla colonna `ultimo_log` è wrappato in un `try/catch (\Throwable)` — cattura sia `\Exception` che `\Error` (es. "Class not found", "Table not found").

2. **Livello Modello** (raccomandazione): Creare il modello `ProcessInstanceLog` con almeno il campo `message` e la migrazione corrispondente. Fino ad allora, il fallback garantisce che la UI non si rompa.

3. **Modello suggerito** (da creare quando disponibile):

```php
// app/Models/ProcessInstanceLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessInstanceLog extends Model
{
    protected $fillable = [
        'process_instance_id',
        'message',
        'level',        // info, warning, error
        'context',      // JSON opzionale
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }
}
```

---

## Integrazione nel pannello (navigazione)

Il gruppo di navigazione "BPM" è definito staticamente tramite la proprietà `$navigationGroup` in entrambe le risorse. Non sono necessarie modifiche all'`AdminPanelProvider`.

Per aggiungere il gruppo esplicitamente con etichetta localizzabile (opzionale), si può aggiungere in `AdminPanelProvider`:

```php
->navigationGroups([
    NavigationGroup::make()->label('BPM'),
])
```

Altrimenti Filament v5 crea il gruppo automaticamente dai valori `$navigationGroup` trovati nelle risorse autodiscoverate.

---

## Schema delle classi — Riepilogo metodi principali

| Classe | Metodi principali |
|---|---|
| `ProcessResource` | `form()`, `table()`, `getRelations()`, `getPages()` |
| `ProcessResource\Pages\CreateProcess` | `afterCreate()` → chiama `$record->updateNextRunDate()` |
| `ProcessResource\Pages\EditProcess` | `afterSave()` → chiama `$record->updateNextRunDate()` |
| `ProcessResource\Pages\ListProcesses` | standard (nessun override) |
| `ProcessTasksRelationManager` | `form()`, `table()` |
| `ProcessInstanceResource` | `form()` (vuoto), `table()`, `getPages()` |
| `ProcessInstanceResource\Pages\ListProcessInstances` | `getHeaderActions()` → `[]` |

---

## Correctness Properties

*Una proprietà è una caratteristica o comportamento che deve valere per tutte le esecuzioni valide del sistema — essenzialmente una dichiarazione formale di ciò che il sistema deve fare. Le proprietà fanno da ponte tra specifiche leggibili dall'uomo e garanzie di correttezza verificabili automaticamente.*

### Proprietà 1: `updateNextRunDate()` imposta `next_run_at` per cron valide

*Per qualsiasi* espressione cron valida a 5 campi e con `is_periodic = true`, l'invocazione di `updateNextRunDate()` su un processo DEVE impostare `next_run_at` a un valore `DateTime` non nullo nel futuro rispetto al momento dell'esecuzione.

**Validates: Requirements 4.2**

### Proprietà 2: `updateNextRunDate()` azzera `next_run_at` per cron non valide o is_periodic false

*Per qualsiasi* combinazione di `is_periodic = false` oppure `cron_expression` non valida (stringa vuota, formato errato, null), l'invocazione di `updateNextRunDate()` DEVE impostare `next_run_at` a `null`.

**Validates: Requirements 4.3**

### Proprietà 3: Risoluzione dell'ultimo log non genera eccezioni

*Per qualsiasi* `ProcessInstance`, incluse quelle prive di log associati o con modello `ProcessInstanceLog` non ancora disponibile, la risoluzione della colonna "Ultimo Log" DEVE restituire una stringa (anche vuota) senza lanciare eccezioni.

**Validates: Requirements 6.3**

### Proprietà 4: Completezza della mappatura dei badge di status

*Per qualsiasi* valore dell'enum `status` di `ProcessInstance` (`pending`, `in_progress`, `completed`, `rejected`, `cancelled`), la colonna `BadgeColumn` DEVE avere un colore definito e non nullo nella mappa `->colors([...])`.

**Validates: Requirements 6.5**
