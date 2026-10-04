<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : I3 — deletes the files of Archived images past the retention period, keeping the database row.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Console\Commands;

use App\Enums\ImageStatus;
use App\Models\EstablishmentImage;
use App\Support\OperationLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeArchivedEstablishmentImages extends Command
{
    protected $signature = 'establishment-images:purge';

    protected $description = 'Deletes the files of Archived establishment images past the retention period (I3) — the database row is kept, only img_path/img_thumbnail_path are cleared.';

    public function handle(): int
    {
        $intRetentionMonths = (int) config('establishment_images.archive_retention_months');
        $dtmCutoff = now()->subMonths($intRetentionMonths);

        $objImagesToPurge = EstablishmentImage::query()
            ->where('img_status', ImageStatus::Archived->value)
            ->whereNotNull('img_archived_at')
            ->where('img_archived_at', '<=', $dtmCutoff)
            ->whereNotNull('img_path')
            ->get();

        $intPurgedCount = 0;

        // Summary comment: one purge per archived image — a file-storage
        // failure on one image must never stop the rest from being purged.
        foreach ($objImagesToPurge as $objImage) {
            $arrBefore = $objImage->getOriginal();

            Storage::disk('local')->delete(array_filter([$objImage->img_path, $objImage->img_thumbnail_path]));

            $objImage->update([
                'img_path' => null,
                'img_thumbnail_path' => null,
            ]);

            OperationLogger::purged('establishment_image', $objImage->img_id, OperationLogger::diff($arrBefore, $objImage));

            $intPurgedCount++;
        } // end foreach objImagesToPurge

        $this->info("Purged {$intPurgedCount} archived establishment image(s).");

        return self::SUCCESS;
    }
}
