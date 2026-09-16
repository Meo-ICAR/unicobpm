<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

class BusinessFunctionMember extends MorphPivot
{
    /**
     * Il nome della tabella nel database.
     */
    protected $table = 'unicobpm.business_function_members';

    /**
     * I campi che possono essere assegnati in massa (Mass Assignment).
     */
    protected $fillable = [
        'business_function_id',
        'employee_type_id',
        'member_type',
        'member_id',
        'is_manager',
    ];

    /**
     * Cast automatico dei tipi di dato.
     */
    protected $casts = [
        'is_manager' => 'boolean',
    ];

    // ==========================================
    //                 RELAZIONI
    // ==========================================

    /**
     * Relazione verso la Funzione di Business.
     */
    public function businessFunction()
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    /**
     * Relazione Polimorfica verso il membro (Employee o Client).
     */
    public function member()
    {
        return $this->morphTo();
    }

    /**
     * Ruolo (EmployeeType) ricoperto dal member in questa funzione aziendale.
     */
    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class);
    }

    /**
     * Il ruolo è esternalizzato quando il member è un consulente (Client)
     * anziché un dipendente interno (Employee). Si confronta con
     * Client::getMorphClass() perché Client non ha un alias registrato in
     * Relation::$morphMap, quindi member_type contiene il nome classe completo.
     */
    public function isOutsourced(): bool
    {
        return $this->member_type === (new Client)->getMorphClass();
    }
}
