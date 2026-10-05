<?php

namespace Tests\Unit;

use App\Models\DocumentType;
use App\Models\ProcessTaskItem;
use App\Neuron\DocumentClassificationResult;
use App\Neuron\DocumentClassifierAgent;
use App\Services\DocumentClassifier;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * DocumentClassifier non deve mai scrivere né leggere DocumentType/Document dal DB reale
 * (vivono su una connessione esterna condivisa con altre applicazioni, mai toccata dai test
 * — vedi ProcessEligibleRecordsCountTest): ogni ProcessTaskItem/DocumentType qui è costruito
 * in memoria con setRelation(), mai persistito.
 */
class DocumentClassifierTest extends TestCase
{
    public function test_matches_a_single_candidate_by_regex_without_calling_ai(): void
    {
        $visura = $this->pendingItem(1, ['name' => 'Visura Camerale', 'regex' => '/visura/i']);
        $documentoIdentita = $this->pendingItem(2, ['name' => 'Documento Identità', 'regex' => '/carta[_-]?identita/i']);

        $result = (new DocumentClassifier)->classify(collect([$visura, $documentoIdentita]), 'irrelevant/path.pdf', 'visura_camerale_2024.pdf');

        $this->assertSame($visura, $result['item']);
        $this->assertSame('procedural', $result['operator_type']);
        $this->assertSame(100, $result['confidence']);
    }

    public function test_does_not_guess_when_regex_matches_more_than_one_candidate_and_ai_is_not_enabled(): void
    {
        $itemA = $this->pendingItem(1, ['name' => 'A', 'regex' => '/doc/i', 'is_AiCheck' => false]);
        $itemB = $this->pendingItem(2, ['name' => 'B', 'regex' => '/doc/i', 'is_AiCheck' => false]);

        $result = (new DocumentClassifier)->classify(collect([$itemA, $itemB]), 'irrelevant/path.pdf', 'documento.pdf');

        $this->assertNull($result['item']);
    }

    public function test_skips_ai_stage_for_unsupported_file_extensions(): void
    {
        $item = $this->pendingItem(1, ['name' => 'A', 'is_AiCheck' => true]);

        $result = (new DocumentClassifier)->classify(collect([$item]), 'irrelevant/path.zip', 'archivio.zip');

        $this->assertNull($result['item']);
    }

    public function test_uses_ai_classification_when_regex_is_not_conclusive(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('documents/foo.pdf', 'contenuto finto del pdf');

        $item = $this->pendingItem(5, ['name' => 'Visura Camerale', 'is_AiCheck' => true, 'AiPattern' => 'Documento CCIAA', 'min_confidence' => 70]);

        $this->fakeClassifierAgent(new DocumentClassificationResult(item_id: 5, confidence: 85, abstract: 'Visura camerale aggiornata'));

        $result = (new DocumentClassifier)->classify(collect([$item]), 'documents/foo.pdf', 'scan.pdf');

        $this->assertSame($item, $result['item']);
        $this->assertSame('ai', $result['operator_type']);
        $this->assertSame(85, $result['confidence']);
        $this->assertSame('Visura camerale aggiornata', $result['abstract']);
    }

    public function test_rejects_ai_classification_below_the_document_types_minimum_confidence(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('documents/foo.pdf', 'contenuto finto del pdf');

        $item = $this->pendingItem(5, ['name' => 'Visura Camerale', 'is_AiCheck' => true, 'min_confidence' => 90]);

        $this->fakeClassifierAgent(new DocumentClassificationResult(item_id: 5, confidence: 60, abstract: null));

        $result = (new DocumentClassifier)->classify(collect([$item]), 'documents/foo.pdf', 'scan.pdf');

        $this->assertNull($result['item']);
    }

    public function test_falls_back_to_no_match_when_the_ai_agent_fails(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('documents/foo.pdf', 'contenuto finto del pdf');

        $item = $this->pendingItem(5, ['name' => 'Visura Camerale', 'is_AiCheck' => true]);

        $this->fakeClassifierAgent(new RuntimeException('API non raggiungibile'));

        $result = (new DocumentClassifier)->classify(collect([$item]), 'documents/foo.pdf', 'scan.pdf');

        $this->assertNull($result['item']);
    }

    private function pendingItem(int $id, array $documentTypeAttributes): ProcessTaskItem
    {
        $item = new ProcessTaskItem(['action_type' => 'document_upload']);
        $item->id = $id;
        $item->setRelation('documentType', new DocumentType($documentTypeAttributes));

        return $item;
    }

    private function fakeClassifierAgent(DocumentClassificationResult|RuntimeException $outcome): void
    {
        $agent = $this->createMock(DocumentClassifierAgent::class);

        if ($outcome instanceof RuntimeException) {
            $agent->method('structured')->willThrowException($outcome);
        } else {
            $agent->method('structured')->willReturn($outcome);
        }

        $this->app->instance(DocumentClassifierAgent::class, $agent);
    }
}
