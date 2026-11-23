<?php

namespace App\Services;

use App\Models\ProcessTaskItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Unico\Core\Documents\Contracts\DocumentReader;

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
        $candidates = $pendingUploadItems->filter(fn (ProcessTaskItem $item) => (bool) $item->documentType?->is_ai_check);

        $mime = $this->guessMime($originalFilename);

        if ($candidates->isEmpty() || ! $mime) {
            return $this->noMatch();
        }

        try {
            $content = Storage::disk('public')->get($storedDiskPath);
            $reader = app(DocumentReader::class);

            if ($content === null || ! $reader->enabled()) {
                return $this->noMatch();
            }

            // Classificazione di unico-core: testo o prime pagine ridotte con il modello economico, cache per impronta del file e
            // modello principale solo se non riconosce. Ogni candidato è un'azione di caricamento: l'id passato è quello dell'azione.
            $answer = $reader->classify(
                $candidates->map(fn (ProcessTaskItem $item) => (object) [
                    'id' => $item->id,
                    'name' => $item->documentType->name,
                    'ai_pattern' => $item->documentType->ai_pattern,
                ])->values(),
                $mime,
                $content,
            );
        } catch (Throwable $e) {
            Log::warning('DocumentClassifier: classificazione AI fallita.', [
                'filename' => $originalFilename,
                'error' => $e->getMessage(),
            ]);

            return $this->noMatch();
        }

        if (! $answer || ! $answer['type_id']) {
            return $this->noMatch();
        }

        $item = $candidates->firstWhere('id', $answer['type_id']);
        $minConfidence = $item?->documentType->min_confidence ?? 70;

        if (! $item || $answer['confidence'] < $minConfidence) {
            return $this->noMatch();
        }

        return [
            'item' => $item,
            'operator_type' => 'ai',
            'confidence' => $answer['confidence'],
            'abstract' => $answer['abstract'],
        ];
    }

    protected function guessMime(string $filename): ?string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
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
