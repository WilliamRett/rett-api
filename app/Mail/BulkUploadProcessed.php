<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class BulkUploadProcessed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $fileName,
        public int $created,
        public int $skipped,
        public int $total,
        public ?string $startedAt = null,
        public ?string $finishedAt = null,
        public ?string $duration = null,
        public array $errors = [],
        public ?string $dashboardUrl = null,
    ) {}

    public function build()
    {
        return $this->subject('Processamento de colaboradores concluído')
            ->view('mail.bulk-upload-processed');
    }
}
