<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Document extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $connection = 'mysql';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->useDisk('public');
    }

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $fillable = [
        'company_id',
        'documentable_type',
        'documentable_id',
        'document_type_id',
        'name',
        'docnumber',
        'spatie_collection',
        'document_url',
        'status',
        'sync_status',
        'source_app',
        'app_id',
        'app_drive_id',
        'app_etag',
        'extracted_text',
        'metadata',
        'ai_abstract',
        'ai_confidence_score',
        'is_template',
        'doctype',
        'cellposition',
        'is_signed',
        'is_unique',
        'is_endMonth',
        'is_monitored',
        'emitted_by',
        'emitted_at',
        'expires_at',
        'delivered_at',
        'signed_at',
        'description',
        'internal_notes',
        'rejection_note',
        'user_id',
        'uploaded_by',
        'verified_by',
        'verified_at',
        'created_by',
        'updated_by',
        'deleted_by',
        'file_hash',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_template' => 'boolean',
        'is_signed' => 'boolean',
        'is_unique' => 'boolean',
        'is_endMonth' => 'boolean',
        'is_monitored' => 'boolean',
        'emitted_at' => 'date',
        'expires_at' => 'date',
        'delivered_at' => 'datetime',
        'signed_at' => 'datetime',
        'verified_at' => 'datetime',
        'ai_confidence_score' => 'integer',
    ];

    /**
     * Relazione: Tipo di documento
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);  // Presume l'esistenza del model DocumentType
    }

    /**
     * I "Booted" del Modello.
     * Intercetta le azioni del ciclo di vita di Eloquent.
     */
    protected static function booted(): void
    {
        static::updating(function (Document $document) {
            if (empty($document->expires_at) && ! empty($document->emitted_at)) {
                $document->expires_at = $document->documentType?->durationCalculate($document->emitted_at);
            }
        });
    }

    /**
     * Relazione: Tenant proprietario
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relazione Polimorfica (es. User, Employee, Contract)
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    // --- Audit & User Relations ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(DocumentReminder::class);
    }

    public function renewedBy($document_id): string
    {
        $nomeDocumento = $this->name;
        $renewedById = $this->documentType()?->renewed_by_id;
        if ($renewedById) {
            $nomeDocumento = DocumentType::find($renewedById)->first()->name;
        }

        return $nomeDocumento;
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days)->toDateString());
    }
}
