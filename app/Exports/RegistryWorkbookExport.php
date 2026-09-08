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

class RegistryWorkbookExport implements WithMultipleSheets, Export
{
    public function sheets(): array
    {
        $members = Member::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('middle_name')
            ->get();

        $loans = MemberLoan::query()->orderBy('cid')->get();

        return [
            new RegistryOfMembersSheet($members),
            new CiContractsSheet($loans),
            new GadRequiredReportSheet($members),
            new NotesSheet,
        ];
    }
}
