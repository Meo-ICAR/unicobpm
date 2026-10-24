<?php

namespace Database\Seeders;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Database\Seeder;

/**
 * Domande dei questionari usati dai processi BPM (fill_checklist) e dalla Relazione organizzativa.
 * Idempotente su (checklist_id, item_code).
 */
class ChecklistItemSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedChecklist('chk_aml_01', [
            [
                'item_code' => 'aml_doc_identita',
                'ordine' => 10,
                'name' => 'Documento di identità valido',
                'question' => 'Il cliente/esecutore ha un documento di identità e codice fiscale (o tessera sanitaria) in corso di validità?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'aml_is_azienda',
                'ordine' => 20,
                'name' => 'Il soggetto è un\'azienda?',
                'question' => 'Il cliente è una persona giuridica (società) o una persona fisica?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'aml_visura',
                'ordine' => 30,
                'name' => 'Visura camerale',
                'question' => 'È stata acquisita la visura camerale aggiornata (non anteriore a 6 mesi)?',
                'type' => 'boolean',
                'is_required' => true,
                'depends_on_code' => 'aml_is_azienda',
                'depends_on_value' => '1',
            ],
            [
                'item_code' => 'aml_pep',
                'ordine' => 40,
                'name' => 'Persona politicamente esposta (PEP)',
                'question' => 'Il cliente o il titolare effettivo è una persona politicamente esposta o un familiare/convivente di PEP?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'aml_scopo',
                'ordine' => 50,
                'name' => 'Scopo e natura del rapporto',
                'question' => 'Sono stati acquisiti e registrati lo scopo e la natura del rapporto / operazione richiesta?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'aml_rischio',
                'ordine' => 60,
                'name' => 'Livello di rischio',
                'question' => 'Indicare il livello di rischio attribuito (1 basso, 2 medio, 3 alto).',
                'type' => 'number',
                'is_required' => true,
            ],
            [
                'item_code' => 'aml_anomalia',
                'ordine' => 70,
                'name' => 'Indicatori di anomalia',
                'question' => 'Sono emerse anomalie rispetto agli indicatori del Regolamento Antiriciclaggio?',
                'type' => 'boolean',
                'is_required' => true,
                'is_knockout' => true,
                'knockout_value' => '1',
            ],
        ]);

        $this->seedChecklist('chk_trasparenza_01', [
            [
                'item_code' => 'tr_lettera_incarico',
                'ordine' => 10,
                'name' => 'Lettera di incarico collaboratore',
                'question' => 'È stata consegnata la lettera di incarico con i riferimenti OAM del collaboratore e della Società?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'tr_privacy',
                'ordine' => 20,
                'name' => 'Informativa privacy e consensi',
                'question' => 'Sono stati raccolti informativa privacy e relativi consensi?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'tr_foglio_informativo',
                'ordine' => 30,
                'name' => 'Foglio informativo',
                'question' => 'È stato consegnato il foglio informativo del servizio di mediazione (condizioni economiche, rischi, clausole)?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'tr_tegm',
                'ordine' => 40,
                'name' => 'TEGM / fogli prodotti eroganti',
                'question' => 'Sono stati forniti TEGM e/o i fogli informativi (o i link) dei prodotti degli istituti eroganti?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'tr_contratto_mediazione',
                'ordine' => 50,
                'name' => 'Contratto di mediazione',
                'question' => 'Il contratto di mediazione riporta provvigione, spese, penali, durata, tipologia di finanziamento e documenti richiesti al cliente?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'tr_firma',
                'ordine' => 60,
                'name' => 'Firma / ricevuta di consegna',
                'question' => 'Esiste ricevuta di consegna o firma (anche OTP) della documentazione precontrattuale?',
                'type' => 'boolean',
                'is_required' => true,
            ],
        ]);

        $this->seedChecklist('chk_oam_01', [
            [
                'item_code' => 'oam_iscrizione',
                'ordine' => 10,
                'name' => 'Iscrizione elenco OAM',
                'question' => 'Il collaboratore/dipendente risulta iscritto nella sezione corretta dell\'elenco OAM?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'oam_onorabilita',
                'ordine' => 20,
                'name' => 'Requisiti di onorabilità',
                'question' => 'Casellario e carichi pendenti (o dichiarazione sostitutiva) sono in corso di validità (non anteriore a 45 giorni)?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'oam_professionalita',
                'ordine' => 30,
                'name' => 'Requisiti di professionalità',
                'question' => 'Titolo di studio / prova valutativa / formazione iniziale risultano acquisiti e archiviati?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'oam_formazione',
                'ordine' => 40,
                'name' => 'Aggiornamento professionale',
                'question' => 'Le ore di aggiornamento OAM dell\'anno in corso rispettano i minimi (15 ore/anno, 60 nel biennio)?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'oam_pec',
                'ordine' => 50,
                'name' => 'PEC personale',
                'question' => 'La PEC personale del collaboratore è dichiarata, verificata e attiva?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'oam_rc',
                'ordine' => 60,
                'name' => 'Polizza RC professionale',
                'question' => 'La copertura RC professionale (societaria o individuale, se dovuta) è attiva con i massimali OAM?',
                'type' => 'boolean',
                'is_required' => true,
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function seedChecklist(string $code, array $items): void
    {
        $checklist = Checklist::query()->where('code', $code)->first();

        if (! $checklist) {
            $this->command?->warn("ChecklistItemSeeder: checklist \"{$code}\" non trovata (esegui prima ChecklistSeeder), skip.");

            return;
        }

        foreach ($items as $item) {
            // Lo schema accetta boolean|text|number|date|select|multiselect (non 'C' legacy).
            if (($item['type'] ?? null) === 'C') {
                $item['type'] = 'boolean';
            }

            ChecklistItem::updateOrCreate(
                ['checklist_id' => $checklist->id, 'item_code' => $item['item_code']],
                $item + ['checklist_id' => $checklist->id]
            );
        }
    }
}
