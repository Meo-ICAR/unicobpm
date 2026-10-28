<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'proxy' => env('PROXY'),  // Optional, will be used for all requests
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
    'bpm' => [
        'url' => env('BPM_API_URL', 'https://unicobpm.hassisto.com'), // Il secondo parametro è un fallback
        // Chiave condivisa inviata (header X-Api-Key) alle API protette degli applicativi esterni.
        'bridge_api_key' => env('BPM_BRIDGE_API_KEY'),
        // Dominio degli identificativi di conversazione delle email dei processi (<bpm-ISTANZA-ESECUZIONE-xxxx@dominio>); vuoto = host di APP_URL.
        'thread_domain' => env('BPM_THREAD_DOMAIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminder su dati non aggiornati
    |--------------------------------------------------------------------------
    |
    | Per ogni voce, bpm:check-stale-records chiede all'applicativo esterno
    | (GET /api/models/{model}/latest) l'ultimo record creato e, se il suo
    | created_at risale a più di 'max_age_days' giorni fa, invia un'email a 'recipient'.
    |
    */
    /*
    | Giorni da attendere prima di rimandare l'avviso di un controllo esterno che segnala ancora il problema, per grado: allo scadere
    | l'avviso sale al grado successivo (regular > warning > alert). Modificabili per processo (campo «Ripetizione avvisi») e per azione.
    */
    'reminder_repeat_days' => ['regular' => 7, 'warning' => 3, 'alert' => 2],

    'fallback_recipient' => env('BPM_FALLBACK_EMAIL', 'sergio.bracale@races.it'),

    'stale_record_reminders' => [
        [
            'app' => 'unicocoge',
            'model' => 'sales_invoice',
            'label' => 'Fatture di vendita',
            'max_age_days' => 30,
            'recipient' => env('STALE_RECORDS_REMINDER_TO', 'rino.muscetti@races.it'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Applicativi esterni interrogabili/scrivibili da UnicoBPM
    |--------------------------------------------------------------------------
    |
    | UnicoFinance (istruttoria, al posto di UnicoLoan dismesso), UnicoCoge (contabilità, al posto di Proforma) e UnicoOAM
    | espongono le stesse API generiche (/api/pratiche/{id}, /api/models/{model}/fields,
    | /api/models/{model}/{id}). La configurazione di un Process (completion_write_app)
    | o di un ProcessTaskItem 'blacklist_check' (config['app']) indica quale
    | interrogare/scrivere; il default applicato nel codice se non specificato
    | è 'unicofinance'.
    |
    */
    'apps' => [
        // Nessun indirizzo di ripiego: senza UNICOFINANCE_API_URL l'app risulta sconosciuta invece di chiamare la produzione.
        'unicofinance' => [
            'url' => env('UNICOFINANCE_API_URL'),
            'label' => 'UnicoFinance',
            // Il token di accesso parte in POST (non resta nella cronologia né nei log): l'app lo accetta così.
            'sso_post' => true,
        ],
        'unicooam' => [
            'url' => env('UNICOOAM_API_URL', 'https://unicooam.hassisto.com'),
            'label' => 'UnicoOAM',
        ],
        // UnicoCoge è il nuovo nome di Proforma. Nessun indirizzo di ripiego: senza UNICOCOGE_API_URL l'app risulta sconosciuta.
        'unicocoge' => [
            'url' => env('UNICOCOGE_API_URL'),
            'label' => 'UnicoCoge',
        ],
        'unicogdpr' => [
            'url' => env('UNICOGDPR_API_URL', 'https://unicogdpr.hassisto.com'),
            'label' => 'UnicoGDPR',
        ],
        'daishboard' => [
            'url' => env('DAISHBOARD_API_URL', 'https://daishboard.hassisto.com'),
            'label' => 'Dashboard',
        ],
        'whistle' => [
            'url' => env('WHISTLE_API_URL', 'https://whistle.unicocompilance.it'),
            'label' => 'WhistleBlowing',
        ],
        'unicoaiact' => [
            'url' => env('UNICOAIACT_API_URL', 'https://unicoaiact.unicocompilance.it'),
            'label' => 'AI ACT',
        ],
    ],

];
