<?php

namespace App\Models;

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
     * Relazione Polimorfica verso il membro (Employee o Consultant).
     */
    public function member()
    {
        return $this->morphTo();
    }
}
