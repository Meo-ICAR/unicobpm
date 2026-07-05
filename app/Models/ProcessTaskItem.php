<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessTaskItem extends Model
{
    protected $fillable = [
        'process_task_id',
        'name',
        'ordine',
        'action_type',
        'is_required',
        'document_type_id',
        'handler_job',
        'config', // Abilitato nel mass assignment
    ];

    protected function casts(): array
    {
        return [
            'ordine' => 'integer',
            'is_required' => 'boolean',
            'config' => 'array', // Converte automaticamente il JSON in un array PHP e viceversa
        ];
    }

    /**
     * Relazione inversa con il Task padre.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class, 'process_task_id');
    }

    /**
     * Relazione con il tipo di documento richiesto (se applicabile).
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Compila un testo stringa (es. URL, oggetto email o corpo email)
     * sostituendo i placeholder racchiusi tra graffe {...} con i dati reali della pratica.
     * * Esempio placeholder: {id}, {status}, {subject.name}, {subject.email}
     *
     * @param  string|null  $template  Il testo grezzo prelevato da $this->config
     * @param  ProcessInstance  $instance  L'istanza runtime della pratica corrente
     */
    public function compileTemplate(?string $template, ProcessInstance $instance): string
    {
        if (empty($template)) {
            return '';
        }

        // Cerca i pattern tipo {chiave} o {relazione.chiave}
        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($instance) {
            $path = trim($matches[1]);

            // Se il placeholder richiede dati dell'entità polimorfica 'subject' (es: Agent)
            // e la relazione non è ancora in memoria, la carichiamo al volo per ottimizzare
            if (str_starts_with($path, 'subject.') && ! $instance->relationLoaded('subject')) {
                $instance->load('subject');
            }

            // data_get estrae i dati in dot-notation sia da oggetti Eloquent che dalle loro relazioni.
            // Se non trova il campo o la relazione, restituisce una stringa vuota invece di andare in crash.
            return data_get($instance, $path, '');

        }, $template);
    }
}
