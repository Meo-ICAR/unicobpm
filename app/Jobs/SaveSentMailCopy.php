<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Webklex\PHPIMAP\ClientManager;

/**
 * Salva nella cartella «Posta inviata» (IMAP) della casella mittente una copia del messaggio spedito via SMTP, che il server non
 * archivia da solo. I parametri sono quelli IMAP_* del .env; la cartella è `mail.sent_folder` (OVH: «Sent»).
 */
class SaveSentMailCopy implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $rawMessage) {}

    public function handle(): void
    {
        $account = (array) config('unico-core.practice_intake.env_mail_account');

        $client = (new ClientManager)->make([
            'host' => $account['imap_host'],
            'port' => (int) $account['imap_port'],
            'encryption' => $account['imap_encryption'] === 'none' ? false : $account['imap_encryption'],
            'validate_cert' => true,
            'username' => $account['imap_username'],
            'password' => $account['imap_password'],
            'protocol' => 'imap',
        ]);
        $client->connect();

        try {
            $client->getFolder((string) config('mail.sent_folder', 'Sent'))->appendMessage($this->rawMessage, ['\\Seen']);
        } finally {
            $client->disconnect();
        }
    }
}
