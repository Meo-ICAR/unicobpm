<?php

namespace App\Models;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Pivot;
use Illuminate\Support\Facades\Log;

class Task extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $orderBy = 'name';
    protected $orderDirection = 'asc';
    protected $fillable = ['name', 'description', 'taskable', 'trigger_field', 'trigger_state', 'trigger_value', 'exclude_field', 'exclude_state', 'exclude_value', 'is_active'];

    /**
     * I tipi di documento associati a questo Task
     */
    public function documentTypes()
    {
        return $this
            ->belongsToMany(DocumentType::class, 'task_document_types')
            ->using(TaskDocumentType::class)  // <-- Usa il nuovo modello Pivot
            ->withPivot('slug', 'is_required')
            ->withTimestamps();
    }

    /**
     * Crea la documentazione mancante per questo specifico task.
     *
     * @param int $companyId ID dell'azienda principale
     * @param int $documentableId ID del record di destinazione (ID Azienda o ID Fornitore)
     * @param bool $is_debug Abilita il debug
     * @return int Numero di documenti creati
     */
    public function createDocumentation(string $companyId, string $documentableId, bool $is_debug = false): int
    {
        $createdCount = 0;

        // Clicliamo sui documentTypes già caricati in memoria
        foreach ($this->documentTypes as $documentType) {
            // 1. Estraiamo SOLO i campi del template che la tabella 'documents' è in grado di accogliere
            $templateData = collect($documentType->toArray())
                ->only([
                    'name',
                    'description',
                    'emitted_by',
                    'is_template',
                    'is_signed',
                    'is_monitored',
                    'doctype'
                ])
                ->toArray();

            if ($is_debug) {
                $emesso = now()->subDays(rand(3, 400));
                $templateData['emitted_at'] = $emesso;
                // 2. Set emitted_at for monitored documents
                if ($documentType->is_monitored) {
                    $scade = $documentType->durationCalculate($emesso);
                    $templateData['expires_at'] = $scade;
                }
            }
            // 3. Uniamo lo stato iniziale richiesto
            $creationData = array_merge($templateData, [
                'status' => 'pending',
            ]);

            // 4. Eseguiamo il firstOrCreate in sicurezza
            $document = Document::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'documentable_type' => $this->taskable,
                    'documentable_id' => $documentableId,
                    'document_type_id' => $documentType->id,
                ],
                $creationData
            );

            if ($document->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        return $createdCount;
    }

    public static function getAvailableFor($record)
    {
        // Trova il tipo (es. "App\Models\Fornitore" diventa "fornitore")
        $taskableType = strtolower(class_basename($record));

        // 1. Prendi tutti i task legati a quel tipo di modello
        $tasks = self::where('taskable', $taskableType)->where('is_active', true)->get();

        // 2. Filtra i task in base allo stato dei campi del record
        return $tasks->filter(function ($task) use ($record) {
            // Verifica condizioni di esclusione
            if (!empty($task->exclude_field)) {
                $excludeValue = $record->{$task->exclude_field};

                // Condizione di esclusione: il campo deve essere valorizzato
                if ($task->exclude_state === 'filled') {
                    if (!empty($excludeValue)) {
                        return false;
                    }
                }

                // Condizione di esclusione: il campo deve essere vuoto
                if ($task->exclude_state === 'empty') {
                    if (empty($excludeValue)) {
                        return false;
                    }
                }

                // Condizione di esclusione: il campo deve essere uguale a un valore specifico
                if ($task->exclude_state === 'equals') {
                    if ($excludeValue == $task->exclude_value) {
                        return false;
                    }
                }
            }

            // Se il task non ha condizioni di attivazione particolari, è sempre valido
            if (empty($task->trigger_field)) {
                return true;
            }

            $fieldValue = $record->{$task->trigger_field};

            // Condizione: il campo deve essere valorizzato (NOT NULL)
            if ($task->trigger_state === 'filled') {
                return !empty($fieldValue);
            }

            // Condizione: il campo deve essere vuoto (NULL)
            if ($task->trigger_state === 'empty') {
                return empty($fieldValue);
            }

            // Condizione: il campo deve essere uguale a un valore specifico
            if ($task->trigger_state === 'equals') {
                return $fieldValue == $task->trigger_value;
            }

            return true;
        });
    }
}
