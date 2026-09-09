<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\RegistryImporter;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index()
    {
        return view('imports.index', [
            'batches' => ImportBatch::with('user')->latest('id')->paginate(15),
        ]);
    }

    public function members(Request $request, RegistryImporter $importer)
    {
        return $this->handle($request, $importer, 'members');
    }

    public function loans(Request $request, RegistryImporter $importer)
    {
        return $this->handle($request, $importer, 'loans');
    }

    protected function handle(Request $request, RegistryImporter $importer, string $type)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:51200'],
            'period' => ['required', 'date_format:Y-m'],
        ]);

        // One combined extract; each row is filed under its own Branch Code column,
        // and tagged with the selected data month.
        $batch = $importer->run($request->file('file'), $type, $data['period'], null, $request->user()?->id);

        if ($batch->status === 'failed') {
            return back()->with('error', 'Import failed: '.collect($batch->errors)->first());
        }

        $month = $batch->period?->translatedFormat('F Y');
        $where = $batch->branch ? " ({$batch->branch})" : '';
        $msg = ucfirst($type)." import for {$month}{$where} — "
            ."{$batch->rows_created} created, {$batch->rows_updated} updated, {$batch->rows_skipped} skipped.";

        return back()->with($batch->rows_skipped > 0 ? 'warning' : 'success', $msg);
    }
}
