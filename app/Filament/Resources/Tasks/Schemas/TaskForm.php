<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Filament\Utils\FormHelper;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema; // Importi il tuo helper
use Illuminate\Database\Eloquent\Builder;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ==========================================
                // ANAGRAFICA BASE
                // ==========================================
                TextInput::make('name')
                    ->label('Nome attività')
                    ->required()
                    ->maxLength(255),

                TextInput::make('description')
                    ->label('Descrizione')
                    ->maxLength(255),

                // ==========================================
                // NUOVA SEZIONE: CONTESTO E STRUTTURA WORKFLOW
                // ==========================================
                Section::make('Inquadramento e Struttura')
                    ->description('Definisci a quale entità si riferisce il task, l\'applicazione di pertinenza e l\'eventuale dipendenza gerarchica.')
                    ->columnSpanFull()
                    ->schema([
                        // 1. RICHIAMO DEL SELECT DINAMICO DAL MORPHMAP
                        FormHelper::polymorphicSelect(name: 'taskable', label: 'Collegata a'),

                        // Relazione gerarchica per i task figli
                        Select::make('parent_id')
                            ->label('Attività precedente (Padre)')
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'name',
                                // Evitiamo che un task possa essere padre di se stesso in fase di modifica
                                modifyQueryUsing: fn (Builder $query, $record) => $record
                                    ? $query->where('id', '!=', $record->id)
                                    : $query
                            )
                            ->placeholder('Nessuna (Questo è un task principale / Radice)')
                            ->searchable()
                            ->helperText('Seleziona un task padre se questa attività deve attivarsi solo al completamento di quella precedente.'),

                        Select::make('company_id')
                            ->label('Company')
                            ->relationship('company', 'name')
                            ->placeholder('Tutte le company')
                            ->searchable()
                            ->preload()
                            ->helperText('Se valorizzata, il plico vale solo per questa company.'),

                        Select::make('app_identifier')
                            ->label('Applicazione di pertinenza')
                            ->options([
                                'UnicoOAM' => 'UnicoOAM (Pannello Amministrazione)',
                                'UnicoFin' => 'UnicoFin (Pannello Finanziario)',
                            ])
                            ->placeholder('Tutte le applicazioni'),

                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),

                        // ==========================================
                        // SEZIONE: FILTRI DI ATTIVAZIONE
                        // ==========================================
                        Section::make('Regole di Attivazione Dinamica')
                            ->description('Il plico si applica ai record del modello collegato che soddisfano entrambe le condizioni (se valorizzate).')
                            ->columnSpanFull()
                            ->collapsed()
                            ->schema([
                                TextInput::make('trigger_field')
                                    ->label('Condizione 1 · campo')
                                    ->placeholder('es. tipo_prodotto')
                                    ->helperText('Nome esatto del campo sul modello collegato.'),
                                Select::make('trigger_state')
                                    ->label('Condizione 1 · stato')
                                    ->options([
                                        'empty' => 'Deve essere VUOTO (Null / Vuoto)',
                                        'filled' => 'Deve essere COMPILATO (Contiene un valore)',
                                        'equals' => 'Deve essere UGUALE a un valore specifico',
                                    ])
                                    ->live(),
                                TextInput::make('trigger_value')
                                    ->label('Condizione 1 · valore')
                                    ->placeholder('es. esterno, attivo')
                                    ->visible(fn (Get $get) => $get('trigger_state') === 'equals')
                                    ->required(fn (Get $get) => $get('trigger_state') === 'equals'),
                                Select::make('tipoprodotto_id')
                                    ->label('Tipo prodotto')
                                    ->relationship('tipoprodotto', 'name')
                                    ->placeholder('Nessuno')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Per i trigger su tipo_prodotto: lega il plico al prodotto per riferimento.'),
                                TextInput::make('trigger_subfield')
                                    ->label('Condizione 2 · campo')
                                    ->placeholder('es. stato_pratica')
                                    ->live(onBlur: true),
                                TextInput::make('trigger_subvalue')
                                    ->label('Condizione 2 · deve essere uguale a')
                                    ->placeholder('es. PERFEZIONATA')
                                    ->required(fn (Get $get) => filled($get('trigger_subfield')))
                                    ->visible(fn (Get $get) => filled($get('trigger_subfield'))),
                            ])
                            ->columns(3),

                        // ==========================================
                        // SEZIONE: FILTRI DI ESCLUSIONE
                        // ==========================================
                        Section::make('Regole di Esclusione Dinamica')
                            ->description('Configura le condizioni per cui questo task deve essere escluso in base ai dati del record.')
                            ->columnSpanFull()
                            ->collapsed()
                            ->schema([
                                TextInput::make('exclude_field')
                                    ->label('Nome colonna del Database')
                                    ->placeholder('es. data_dimissione, tipo_fornitore')
                                    ->helperText("Inserisci il nome esatto del campo sulla tabella dell'entità."),
                                Select::make('exclude_state')
                                    ->label('Condizione del campo')
                                    ->options([
                                        'empty' => 'Deve essere VUOTO (Null / Vuoto)',
                                        'filled' => 'Deve essere COMPILATO (Contiene un valore)',
                                        'equals' => 'Deve essere UGUALE a un valore specifico',
                                    ])
                                    ->live(),
                                TextInput::make('exclude_value')
                                    ->label('Valore specifico richiesto')
                                    ->placeholder('es. esterno, attivo')
                                    ->visible(fn (Get $get) => $get('exclude_state') === 'equals')
                                    ->required(fn (Get $get) => $get('exclude_state') === 'equals'),
                            ])
                            ->columns(3),
                        Section::make('File Allegato')
                            ->components([
                                SpatieMediaLibraryFileUpload::make('attachments')
                                    ->label('Carica file (PDF, immagini, Word)')
                                    ->multiple()
                                    ->collection('documents')
                                    ->disk('public')
                                    ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                    ->maxSize(20480)
                                    ->columnSpanFull(),
                            ]),

                        // ==========================================
                        // SEZIONE DOCUMENTI (Esistente)
                        // ==========================================
                        Section::make('Associazione documenti')
                            ->description("Documenti richiesti dall'attività. Il task figlio eredita quelli del padre, salvo stesso tipo.")
                            ->columnSpanFull()
                            ->schema([
                                Repeater::make('documentRequirements')
                                    ->label('Documenti richiesti')
                                    ->relationship('documentRequirements')
                                    ->orderColumn('sort_order')
                                    ->defaultItems(0)
                                    ->schema([
                                        Select::make('document_type_id')
                                            ->label('Tipo documento')
                                            ->relationship('documentType', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->distinct()
                                            ->columnSpan(2),
                                        Select::make('documentable')
                                            ->label('Documento su')
                                            ->options(['client' => 'Cliente della pratica'])
                                            ->placeholder('Il modello del plico')
                                            ->live(),
                                        Select::make('client_roles')
                                            ->label('Ruoli nella pratica')
                                            ->options(['richiedente' => 'Richiedente', 'coobbligato' => 'Coobbligato', 'garante' => 'Garante', 'amministratore' => 'Amministratore'])
                                            ->multiple()
                                            ->placeholder('Tutti')
                                            ->helperText('Persona fisica o giuridica: lo decide il tipo documento.')
                                            ->visible(fn (Get $get) => $get('documentable') === 'client'),
                                        Select::make('signer_model')
                                            ->label('Chi firma')
                                            ->options(['client' => 'Cliente', 'fornitore' => 'Agente / produttore', 'employee' => 'Dipendente', 'company' => 'Azienda'])
                                            ->placeholder('Come il tipo documento')
                                            ->helperText('Es. l\'agente nell\'onboarding.'),
                                        Toggle::make('is_required')
                                            ->label('Obbligatorio')
                                            ->default(true)
                                            ->inline(false),
                                    ])
                                    ->columns(6)
                                    ->addActionLabel('Aggiungi documento')
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
