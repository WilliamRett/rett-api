<?php

namespace App\Jobs;

use App\Services\Collaborator\Contract\CollaboratorServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCollaboratorsCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId, public string $path) {}

    public function handle(): void
    {
        app(CollaboratorServiceContract::class)->importFromCsvForUser($this->userId, $this->path);
    }
}
