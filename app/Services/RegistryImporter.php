<?php

namespace App\Services;

use App\Imports\LoansImport;
use App\Imports\MembersImport;
use App\Models\ImportBatch;
use App\Models\Member;
use App\Support\Registry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class RegistryImporter
{
    /**
     * @param  'members'|'loans'  $type
     * @param  string|null  $period  data month as "YYYY-MM" — stamped on every row
     * @param  string|null  $branchOverride  optional — force every row to this branch;
     *                                       normally null so each row is filed under its
     *                                       own "Branch Code" column from the extract
     */
    public function run(
        UploadedFile $file,
        string $type,
        ?string $period = null,
        ?string $branchOverride = null,
        ?int $userId = null,
    ): ImportBatch {
        $batch = ImportBatch::create([
            'type' => $type,
            'period' => Registry::periodToDate($period)?->toDateString(),
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'processing',
            'user_id' => $userId,
        ]);

        try {
            $import = $type === 'loans'
                ? new LoansImport($branchOverride, $period)
                : new MembersImport($branchOverride, $period);

            Excel::import($import, $file);

            $branches = array_keys($import->branchesSeen);
            sort($branches);

            if ($import instanceof LoansImport && $import->touchedCids) {
                Member::recomputeAggregates(array_keys($import->touchedCids));
            }

            $batch->update([
                'status' => 'completed',
                'branch' => match (true) {
                    count($branches) === 1 => $branches[0],
                    count($branches) > 1 => Str::limit(implode(', ', $branches), 240),
                    default => null,
                },
                'rows_created' => $import->created,
                'rows_updated' => $import->updated,
                'rows_skipped' => $import->skipped,
                'rows_total' => $import->created + $import->updated + $import->skipped,
                'errors' => $import->errors ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Registry import failed', ['batch' => $batch->id, 'exception' => $e]);

            $batch->update([
                'status' => 'failed',
                'errors' => [$e->getMessage()],
            ]);
        }

        return $batch->refresh();
    }
}
