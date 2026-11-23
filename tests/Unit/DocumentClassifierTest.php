<?php

namespace Tests\Unit;

use App\Models\DocumentType;
use App\Models\ProcessTaskItem;
use App\Services\DocumentClassifier;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Unico\Core\Documents\Contracts\DocumentReader;

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
        $itemA = $this->pendingItem(1, ['name' => 'A', 'regex' => '/doc/i', 'is_ai_check' => false]);
        $itemB = $this->pendingItem(2, ['name' => 'B', 'regex' => '/doc/i', 'is_ai_check' => false]);

        $result = (new DocumentClassifier)->classify(collect([$itemA, $itemB]), 'irrelevant/path.pdf', 'documento.pdf');

        $this->assertNull($result['item']);
    }

    public function test_skips_ai_stage_for_unsupported_file_extensions(): void
    {
        $item = $this->pendingItem(1, ['name' => 'A', 'is_ai_check' => true]);

        $result = (new DocumentClassifier)->classify(collect([$item]), 'irrelevant/path.zip', 'archivio.zip');

        $this->assertNull($result['item']);
    }

    public function test_uses_ai_classification_when_regex_is_not_conclusive(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('documents/foo.pdf', 'contenuto finto del pdf');

        $item = $this->pendingItem(5, ['name' => 'Visura Camerale', 'is_ai_check' => true, 'ai_pattern' => 'Documento CCIAA', 'min_confidence' => 70]);

        $this->fakeReader(['type_id' => 5, 'confidence' => 85, 'abstract' => 'Visura camerale aggiornata']);

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

        $item = $this->pendingItem(5, ['name' => 'Visura Camerale', 'is_ai_check' => true, 'min_confidence' => 90]);

        $this->fakeReader(['type_id' => 5, 'confidence' => 60, 'abstract' => null]);

        $result = (new DocumentClassifier)->classify(collect([$item]), 'documents/foo.pdf', 'scan.pdf');

        $this->assertNull($result['item']);
    }

    public function test_falls_back_to_no_match_when_the_ai_agent_fails(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('documents/foo.pdf', 'contenuto finto del pdf');

        $item = $this->pendingItem(5, ['name' => 'Visura Camerale', 'is_ai_check' => true]);

        $this->fakeReader(new RuntimeException('API non raggiungibile'));

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

    /** Lettore di unico-core finto: risponde con la classificazione data (o solleva l'errore) e non chiama nessun servizio. */
    private function fakeReader(array|RuntimeException $outcome): void
    {
        $this->app->instance(DocumentReader::class, new class($outcome) implements DocumentReader
        {
            public function __construct(private array|RuntimeException $outcome) {}

            public function enabled(): bool
            {
                return true;
            }

            public function read(string $kind, string $mime, string $bytes): ?array
            {
                return null;
            }

            public function extract(string $source, string $system, string $prompt, array $schema, string $mime, string $bytes, ?string $knownText = null, ?callable $complete = null, ?string $kind = null): ?array
            {
                return null;
            }

            public function classify(Collection $candidates, string $mime, string $bytes): ?array
            {
                if ($this->outcome instanceof RuntimeException) {
                    throw $this->outcome;
                }

                return $this->outcome;
            }

            public function lastUsage(): ?array
            {
                return null;
            }
        });
    }
}
