<?php

namespace App\Exports;

use App\Exports\Sheets\GadRequiredReportSheet;
use App\Models\Member;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GadReportExport implements Export, WithMultipleSheets
{
    public function __construct(protected ?string $branch = null, protected ?string $period = null) {}

    public function sheets(): array
    {
        $members = Member::query()
            ->when($this->branch, fn ($q, $b) => $q->where('branch', $b))
            ->when($this->period, fn ($q, $p) => $q->whereYear('data_period', substr($p, 0, 4))->whereMonth('data_period', substr($p, 5, 2)))
            ->get();

        return [
            new GadRequiredReportSheet($members),
        ];
    }
}
