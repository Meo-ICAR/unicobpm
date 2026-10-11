<?php

namespace App\Models;

use App\Enums\Severity;
use Unico\Core\Enums\MailType;
use Unico\Core\Mail\EmailTemplateResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessTaskItem extends Model
{
    protected $orderBy = 'ordine';

    protected $fillable = [
        'process_task_id',
        'name',
        'ordine',
        'action_type',
        'is_required',
        'document_type_id',
        'checklist_id',
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
     * La checklist da compilare, se action_type è 'fill_checklist'.
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    /**
     * Recupera il template email configurato per le azioni di tipo 'automated_email',
     * tramite l'ID salvato in $this->config['email_template_id'].
     */
    public function getEmailTemplate(): ?EmailTemplate
    {
        $templateId = $this->config['email_template_id'] ?? null;

        return $templateId ? EmailTemplate::find($templateId) : null;
    }

    /**
     * Per le azioni 'external_check': sceglie, tra i template con $this->config['email_template_code'], il più specifico per company,
     * app (unicobpm) e tipo «processo», del grado indicato o, se manca, del grado inferiore più vicino (Unico\Core\Mail\EmailTemplateResolver).
     * Se la severity è assente prende il template del grado più alto che esiste.
     */
    public function resolveCheckEmailTemplate(?Severity $severity, int|string|null $companyId = null): ?EmailTemplate
    {
        $code = $this->config['email_template_code'] ?? null;

        if (blank($code)) {
            return null;
        }

        $found = app(EmailTemplateResolver::class)->resolve((string) $code, $companyId, 'unicobpm', MailType::Processo, ($severity ?? Severity::Alert)->value);

        return $found === null ? null : EmailTemplate::query()->find($found->getKey());
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
