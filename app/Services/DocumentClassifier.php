<?php

namespace App\Services;

use App\Models\ProcessTaskItem;
use App\Neuron\DocumentClassificationResult;
use App\Neuron\DocumentClassifierAgent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use NeuronAI\Chat\Enums\MediaType;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\FileContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\UserMessage;
use Throwable;

/**
 * Capisce a quale ProcessTaskItem (document_upload) pendente corrisponde un allegato,
 * in due stadi: prima un tentativo deterministico via regex (DocumentType::regex sul nome
 * file), poi — solo se non risolutivo — un fallback AI che legge davvero il contenuto del
 * file. Non forza mai un match: se nessuno stadio è abbastanza sicuro, ritorna "nessun match"
 * così l'allegato resta disponibile per un controllo umano invece di essere assegnato a caso.
 */
class DocumentClassifier
{
    /**
     * @param  Collection<int, ProcessTaskItem>  $pendingUploadItems
     * @return array{item: ?ProcessTaskItem, operator_type: ?string, confidence: ?int, abstract: ?string}
     */
    public function classify(Collection $pendingUploadItems, string $storedDiskPath, string $originalFilename): array
    {
        $regexMatch = $this->matchByRegex($pendingUploadItems, $originalFilename);

        if ($regexMatch) {
            return [
                'item' => $regexMatch,
                'operator_type' => 'procedural',
                'confidence' => 100,
                'abstract' => null,
            ];
        }

        return $this->matchByAi($pendingUploadItems, $storedDiskPath, $originalFilename);
    }

    /**
     * @param  Collection<int, ProcessTaskItem>  $pendingUploadItems
     */
    protected function matchByRegex(Collection $pendingUploadItems, string $filename): ?ProcessTaskItem
    {
        $matches = $pendingUploadItems->filter(function (ProcessTaskItem $item) use ($filename) {
            $pattern = $item->documentType?->regex;

            if (blank($pattern)) {
                return false;
            }

            return @preg_match($pattern, $filename) === 1;
        });

        // Se più di un candidato matcha lo stesso allegato, la regex non è risolutiva:
        // meglio lasciare decidere l'AI (o l'umano) che assegnare a caso.
        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @param  Collection<int, ProcessTaskItem>  $pendingUploadItems
     * @return array{item: ?ProcessTaskItem, operator_type: ?string, confidence: ?int, abstract: ?string}
     */
    protected function matchByAi(Collection $pendingUploadItems, string $storedDiskPath, string $originalFilename): array
    {
        $candidates = $pendingUploadItems->filter(fn (ProcessTaskItem $item) => (bool) $item->documentType?->is_AiCheck);

        $mediaType = $this->guessMediaType($originalFilename);

        if ($candidates->isEmpty() || ! $mediaType) {
            return $this->noMatch();
        }

        try {
            $content = Storage::disk('public')->get($storedDiskPath);

            if ($content === null) {
                return $this->noMatch();
            }

            $descriptions = $candidates
                ->map(fn (ProcessTaskItem $item) => sprintf(
                    '- item_id %d: "%s" — %s',
                    $item->id,
                    $item->documentType->name,
                    $item->documentType->AiPattern ?: 'nessuna descrizione fornita'
                ))
                ->implode("\n");

            $instructions = "Classifica il documento allegato tra questi candidati:\n{$descriptions}\n\n".
                'Se non corrisponde chiaramente a nessuno, usa item_id null e confidence bassa.';

            /** @var DocumentClassificationResult $result */
            $result = app(DocumentClassifierAgent::class)->structured(
                new UserMessage([
                    new TextContent($instructions),
                    new FileContent(base64_encode($content), SourceType::BASE64, $mediaType, $originalFilename),
                ]),
                DocumentClassificationResult::class,
            );
        } catch (Throwable $e) {
            Log::warning('DocumentClassifier: classificazione AI fallita.', [
                'filename' => $originalFilename,
                'error' => $e->getMessage(),
            ]);

            return $this->noMatch();
        }

        if (! $result->item_id) {
            return $this->noMatch();
        }

        $item = $candidates->firstWhere('id', $result->item_id);
        $minConfidence = $item?->documentType->min_confidence ?? 70;

        if (! $item || $result->confidence < $minConfidence) {
            return $this->noMatch();
        }

        return [
            'item' => $item,
            'operator_type' => 'ai',
            'confidence' => $result->confidence,
            'abstract' => $result->abstract,
        ];
    }

    protected function guessMediaType(string $filename): ?MediaType
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => MediaType::PDF,
            'png' => MediaType::PNG,
            'jpg', 'jpeg' => MediaType::JPEG,
            'gif' => MediaType::GIF,
            'webp' => MediaType::WEBP,
            default => null,
        };
    }

    /**
     * @return array{item: ?ProcessTaskItem, operator_type: ?string, confidence: ?int, abstract: ?string}
     */
    protected function noMatch(): array
    {
        return ['item' => null, 'operator_type' => null, 'confidence' => null, 'abstract' => null];
    }
}
