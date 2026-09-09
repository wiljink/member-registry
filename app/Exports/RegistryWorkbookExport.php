<?php

namespace App\Exports;

use App\Exports\Sheets\CiContractsSheet;
use App\Exports\Sheets\GadRequiredReportSheet;
use App\Exports\Sheets\NotesSheet;
use App\Exports\Sheets\RegistryOfMembersSheet;
use App\Models\Member;
use App\Models\MemberLoan;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RegistryWorkbookExport implements Export, WithMultipleSheets
{
    public function __construct(protected ?string $branch = null, protected ?string $period = null) {}

    public function sheets(): array
    {
        $members = Member::query()
            ->when($this->branch, fn ($q, $b) => $q->where('branch', $b))
            ->when($this->period, fn ($q, $p) => $q->whereYear('data_period', substr($p, 0, 4))->whereMonth('data_period', substr($p, 5, 2)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('middle_name')
            ->get();

        $cids = $members->pluck('cid');
        $loans = MemberLoan::query()
            ->when($this->branch || $this->period, fn ($q) => $q->whereIn('cid', $cids))
            ->orderBy('cid')
            ->get();

        return [
            new RegistryOfMembersSheet($members, $this->period),
            new CiContractsSheet($loans),
            new GadRequiredReportSheet($members),
            new NotesSheet($this->branch, $this->period),
        ];
    }
}
