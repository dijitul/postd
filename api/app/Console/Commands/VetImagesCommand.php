<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Modules\Media\Services\ImageLibraryService;
use Illuminate\Console\Command;

/**
 * Vets library photos that have not been looked at yet.
 *
 * Photos are normally vetted as they are imported. This catches the ones
 * whose check failed at the time (API down, timeout), and backfills photos
 * imported before vetting existed. Unvetted photos are never put on a post,
 * so until this runs they simply wait.
 */
class VetImagesCommand extends Command
{
    protected $signature = 'images:vet
        {--business= : Only this business id}
        {--limit=50 : Most photos to vet in one run}';

    protected $description = 'Vet library photos that have not been checked yet';

    public function handle(ImageLibraryService $library): int
    {
        $business = $this->option('business')
            ? Business::findOrFail($this->option('business'))
            : null;

        $counts = $library->vetPending($business, (int) $this->option('limit'));

        $this->info("Vetted {$counts['vetted']}, switched off {$counts['switched_off']}, failed {$counts['failed']}.");

        return Command::SUCCESS;
    }
}
