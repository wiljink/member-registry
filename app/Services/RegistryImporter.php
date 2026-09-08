<?php

namespace App\Services;

use App\Imports\LoansImport;
use App\Imports\MembersImport;
use App\Models\ImportBatch;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class RegistryImporter
{
    /**
     * @param  'members'|'loans'  $type
     */
    public function run(UploadedFile $file, string $type, ?int $userId = null): ImportBatch
    {
        $batch = ImportBatch::create([
            'type'              => $type,
            'original_filename' => $file->getClientOriginalName(),
            'status'            => 'processing',
            'user_id'           => $userId,
        ]);

        try {
            $import = $type === 'loans' ? new LoansImport : new MembersImport;

            Excel::import($import, $file);

            if ($import instanceof LoansImport && $import->touchedCids) {
                Member::recomputeAggregates(array_keys($import->touchedCids));
            }

            $batch->update([
                'status'       => 'completed',
                'rows_created' => $import->created,
                'rows_updated' => $import->updated,
                'rows_skipped' => $import->skipped,
                'rows_total'   => $import->created + $import->updated + $import->skipped,
                'errors'       => $import->errors ?: null,
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
