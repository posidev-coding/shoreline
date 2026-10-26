<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

/**
 * Livewire cleans up abandoned temporary uploads on a local disk, but not on a bucket,
 * where it expects a lifecycle rule. This does the same job on a schedule instead.
 */
#[Signature('app:prune-uploads {--hours=24 : Delete temporary uploads older than this}')]
#[Description('Delete abandoned Livewire temporary uploads')]
class PruneUploads extends Command
{
    public function handle(): int
    {
        $storage = Storage::disk(FileUploadConfiguration::disk());
        $cutoff = now()->subHours((int) $this->option('hours'))->getTimestamp();
        $deleted = 0;

        foreach ($storage->files(FileUploadConfiguration::directory()) as $path) {
            if ($storage->lastModified($path) < $cutoff) {
                $storage->delete($path);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} abandoned ".str('upload')->plural($deleted).'.');

        return self::SUCCESS;
    }
}
