<?php

namespace App\Http\Controllers;

use App\Exports\GadReportExport;
use App\Exports\RegistryWorkbookExport;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function registry()
    {
        return Excel::download(
            new RegistryWorkbookExport,
            'REGISTRY OF MEMBERS '.now()->format('Y-m-d').'.xlsx'
        );
    }

    public function gad()
    {
        return Excel::download(
            new GadReportExport,
            'GAD REQUIRED REPORT '.now()->format('Y-m-d').'.xlsx'
        );
    }
}
