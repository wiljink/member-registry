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
            'batches' => ImportBatch::with('user')->latest()->paginate(15),
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
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:51200'],
        ]);

        $batch = $importer->run($request->file('file'), $type, $request->user()?->id);

        if ($batch->status === 'failed') {
            return back()->with('error', 'Import failed: '.collect($batch->errors)->first());
        }

        $msg = ucfirst($type)." import done — {$batch->rows_created} created, {$batch->rows_updated} updated, {$batch->rows_skipped} skipped.";

        return back()->with($batch->rows_skipped > 0 ? 'warning' : 'success', $msg);
    }
}
