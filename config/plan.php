<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tipo di piano (installazione)
    |--------------------------------------------------------------------------
    |
    | Se valorizzata, questa variabile ha sempre la precedenza sul piano
    | memorizzato in company_modules per l'azienda dell'utente loggato
    | (vedi App\Listeners\ResolveCompanyPlanTypeOnLogin ed effectivePlanType()
    | in app/helpers.php).
    |
    */

    'type' => env('PLAN_TYPE'),

];
