<?php

namespace App\Http\Controllers;

use App\Exports\GadReportExport;
use App\Exports\RegistryWorkbookExport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function registry(Request $request)
    {
        [$branch, $period] = $this->filters($request);

        return Excel::download(
            new RegistryWorkbookExport($branch, $period),
            $this->filename('REGISTRY OF MEMBERS', $branch, $period),
        );
    }

    public function gad(Request $request)
    {
        [$branch, $period] = $this->filters($request);

        return Excel::download(
            new GadReportExport($branch, $period),
            $this->filename('GAD REQUIRED REPORT', $branch, $period),
        );
    }

    /** @return array{0: ?string, 1: ?string} */
    protected function filters(Request $request): array
    {
        $branch = $request->query('branch') ?: null;
        $period = $request->query('period');
        $period = ($period && preg_match('/^\d{4}-\d{2}$/', $period)) ? $period : null;

        return [$branch, $period];
    }

    protected function filename(string $base, ?string $branch, ?string $period): string
    {
        $parts = array_filter([
            $base,
            $branch ? Str::of($branch)->replace('/', '-') : null,
            $period,
        ]);

        return implode(' - ', $parts).' '.now()->format('Y-m-d').'.xlsx';
    }
}
