<?php

// app/Models/Fornitore.php

namespace App\Models;

use App\Traits\HasBpmTriggers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Unico\Core\Models\Fornitore as CoreFornitore;

/**
 * Agenti di commercio / Collaboratori (Fornitori di servizi).
 * Legge e scrive sulla connessione 'core' -> database unicosuite.
 *
 * NB: la versione originale usava HasUuids (UUID come PK). Nel pacchetto core
 * la PK è bigint auto-increment. Verificare che eventuali UUID già salvati
 * nei campi *_type / *_id delle tabelle BPM vengano aggiornati.
 *
 * Metodi ereditati da CoreFornitore (non ridefinire):
 *   branch(), company(), fornitorirole(), user(), blacklistClientiFornitori(),
 *   complaintRegistry(), pratiches(), provvigioniRules()
 */
class Fornitore extends CoreFornitore
{
    use HasBpmTriggers;
    use HasFactory;

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $fornitore) {
            if (empty($fornitore->company_id) && auth()->check()) {
                $fornitore->company_id = auth()->user()->profile?->company_id;
            }
        });
    }

    /**
     * Cerca un fornitore per nome e ritorna il campo 'nome'.
     */
    public static function getFornitoreNomeByName(string $name): ?string
    {
        $fornitore = static::where('name', $name)->first();

        return $fornitore?->nome;
    }

    /** Morph inverso: profile_type / profile_id nella tabella users. */
    public function profile(): MorphTo
    {
        return $this->morphTo();
    }

    public function websites()
    {
        return $this->morphMany(Website::class, 'websiteable');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function branches()
    {
        return $this->morphMany(Branch::class, 'branchable');
    }

    public function processInstances(): MorphMany
    {
        // 'subject' è il nome del campo polimorfo usato in ProcessInstance (subject_type / subject_id)
        return $this->morphMany(ProcessInstance::class, 'subject');
    }
}
