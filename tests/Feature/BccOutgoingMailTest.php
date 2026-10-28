<?php

namespace Tests\Feature;

use App\Jobs\SaveSentMailCopy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BccOutgoingMailTest extends TestCase
{
    public function test_every_outgoing_email_is_copied_in_bcc_without_duplicating_recipients(): void
    {
        config(['mail.default' => 'array', 'mail.always_bcc' => 'admin@example.com, non-valida ,presente@example.com']);

        Mail::raw('Ciao', fn ($message) => $message->to('dest@example.com')->cc('presente@example.com')->subject('Prova'));

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        $this->assertSame(['admin@example.com'], array_map(fn ($a) => $a->getAddress(), $sent->getBcc()));
        $this->assertSame(['dest@example.com'], array_map(fn ($a) => $a->getAddress(), $sent->getTo()));
    }

    public function test_without_configuration_no_copy_is_added(): void
    {
        config(['mail.default' => 'array', 'mail.always_bcc' => null]);

        Mail::raw('Ciao', fn ($message) => $message->to('dest@example.com')->subject('Prova'));

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        $this->assertSame([], $sent->getBcc());
    }

    public function test_a_copy_goes_to_the_sent_folder_only_for_the_own_mailbox_and_when_enabled(): void
    {
        Queue::fake();
        config(['mail.default' => 'array', 'unico-core.practice_intake.env_mail_account.email_address' => 'casella@example.com']);

        config(['mail.save_sent_copy' => false]);
        Mail::raw('Ciao', fn ($message) => $message->from('casella@example.com')->to('dest@example.com')->subject('Spenta'));
        Queue::assertNothingPushed();

        config(['mail.save_sent_copy' => true]);
        Mail::raw('Ciao', fn ($message) => $message->from('altra@example.com')->to('dest@example.com')->subject('Altra casella'));
        Queue::assertNothingPushed();

        Mail::raw('Ciao', fn ($message) => $message->from('casella@example.com')->to('dest@example.com')->subject('Da salvare'));
        Queue::assertPushed(SaveSentMailCopy::class, fn (SaveSentMailCopy $job): bool => str_contains($job->rawMessage, 'Subject: Da salvare'));
    }
}
