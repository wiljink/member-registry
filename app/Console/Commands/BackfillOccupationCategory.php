<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Support\Registry;
use Illuminate\Console\Command;

/**
 * Fill the manual `occupation_category` column from the raw CIC value
 * (`occupation_category_code`) that the members import now stores per CID.
 * Only touches rows where `occupation_category` is still blank — a staff
 * edit in the member form is never overwritten.
 */
class BackfillOccupationCategory extends Command
{
    protected $signature = 'app:backfill-occupation {--dry-run : report what would change without saving}';

    protected $description = 'Seed members\' occupation_category from the imported CIC code, for rows still blank';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $filled = 0;
        $unmapped = 0;
        $scanned = 0;
        $breakdown = [];

        Member::query()
            ->where(fn ($q) => $q->whereNull('occupation_category')->orWhere('occupation_category', ''))
            ->whereNotNull('occupation_category_code')
            ->where('occupation_category_code', '!=', '')
            ->chunkById(500, function ($members) use (&$filled, &$unmapped, &$scanned, &$breakdown, $dry) {
                foreach ($members as $member) {
                    $scanned++;
                    $label = Registry::occupationCategoryLabel($member->occupation_category_code);

                    if ($label === null) {
                        $unmapped++;

                        continue;
                    }

                    $breakdown[$label] = ($breakdown[$label] ?? 0) + 1;
                    $filled++;

                    if ($dry) {
                        continue;
                    }

                    $member->occupation_category = $label;
                    $member->recomputeCompletion();
                    $member->save();
                }
            });

        ksort($breakdown);
        $this->table(
            ['occupation_category', $dry ? 'would set' : 'set'],
            collect($breakdown)->map(fn ($n, $label) => [$label, number_format($n)])->values(),
        );

        $this->info(sprintf(
            '%s %s of %s scanned row(s); %s had an unmapped/"Other" code.',
            $dry ? 'Would fill' : 'Filled',
            number_format($filled),
            number_format($scanned),
            number_format($unmapped),
        ));

        return self::SUCCESS;
    }
}
