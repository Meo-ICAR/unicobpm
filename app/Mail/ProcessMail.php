<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email di testo dei processi (avvisi dei controlli, email automatiche), con allegati opzionali. */
class ProcessMail extends Mailable
{
    /** @param array<int, string|array{path: string, as?: string}> $files */
    public function __construct(public string $subjectLine, public string $text, public array $files = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.plain', with: ['text' => $this->text]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->files as $file) {
            $path = is_array($file) ? ($file['path'] ?? null) : $file;

            if (! $path || ! file_exists($path)) {
                continue;
            }

            $attachment = Attachment::fromPath($path);
            $attachments[] = is_array($file) && isset($file['as']) ? $attachment->as($file['as']) : $attachment;
        }

        return $attachments;
    }
}
