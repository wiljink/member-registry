<?php

namespace App\Exports;

use App\Exports\Sheets\GadRequiredReportSheet;
use App\Models\Member;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GadReportExport implements WithMultipleSheets, Export
{
    public function sheets(): array
    {
        return [
            new GadRequiredReportSheet(Member::query()->get()),
        ];
    }
}
