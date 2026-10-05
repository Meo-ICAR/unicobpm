<?php

declare(strict_types=1);

namespace App\Neuron;

use App\Models\AiActionDraft;
use App\Models\Fornitore;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * Prepara (non invia) una bozza di email di sollecito verso un Fornitore, identificato per
 * nome. L'invio effettivo richiede la conferma esplicita di un operatore umano dalla pagina
 * dell'assistente: questo tool non spedisce mai un'email da solo, perché un nome interpretato
 * male o un testo non rivisto diventerebbe un'email reale non annullabile.
 */
class PrepareReminderEmailTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'prepare_reminder_email',
            "Prepara una bozza di email di sollecito verso un Fornitore (produttore/agente), identificato per nome. Non invia l'email: crea solo una bozza in attesa di conferma da parte di un operatore umano."
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'fornitore_nome',
                type: PropertyType::STRING,
                description: 'Nome (anche parziale) del Fornitore/produttore a cui inviare il sollecito.',
                required: true,
            ),
            new ToolProperty(
                name: 'messaggio',
                type: PropertyType::STRING,
                description: 'Cosa sollecitare/comunicare (es. "documentazione mancante per la pratica X").',
                required: true,
            ),
        ];
    }

    public function __invoke(string $fornitore_nome, string $messaggio): string
    {
        $candidates = Fornitore::query()
            ->where(function ($query) use ($fornitore_nome) {
                $query->where('name', 'like', "%{$fornitore_nome}%")
                    ->orWhere('nome', 'like', "%{$fornitore_nome}%");
            })
            ->limit(6)
            ->get();

        if ($candidates->isEmpty()) {
            return "Nessun fornitore trovato con nome simile a \"{$fornitore_nome}\". Chiedi all'utente di controllare il nome.";
        }

        if ($candidates->count() > 1) {
            $list = $candidates
                ->map(fn (Fornitore $f) => '- '.$f->name.($f->nome && $f->nome !== $f->name ? " ({$f->nome})" : ''))
                ->implode("\n");

            return "Più fornitori corrispondono a \"{$fornitore_nome}\": chiedi all'utente di specificare quale, tra questi:\n{$list}";
        }

        $fornitore = $candidates->first();
        $email = $fornitore->email ?: $fornitore->email_private;

        if (blank($email)) {
            return "Il fornitore \"{$fornitore->name}\" non ha un indirizzo email registrato: non posso preparare la bozza.";
        }

        $subject = "Sollecito documentazione — {$fornitore->name}";
        $body = "Gentile {$fornitore->name},\n\n{$messaggio}\n\nRestiamo in attesa di un vostro riscontro.\n\nCordiali saluti.";

        $draft = AiActionDraft::create([
            'type' => 'send_email',
            'payload' => [
                'to' => $email,
                'to_name' => $fornitore->name,
                'fornitore_id' => $fornitore->id,
                'subject' => $subject,
                'body' => $body,
            ],
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        return "Bozza email pronta (ID {$draft->id}) per {$fornitore->name} <{$email}>, oggetto: \"{$subject}\". Resta in attesa di conferma dell'operatore prima dell'invio: dillo all'utente.";
    }
}
