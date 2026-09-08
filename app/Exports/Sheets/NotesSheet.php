<?php

namespace App\Exports\Sheets;

use App\Support\Registry;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class NotesSheet implements FromCollection, WithTitle, WithEvents
{
    public function collection(): Collection
    {
        return collect();
    }

    public function title(): string
    {
        return 'Notes';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->setCellValue('A1', 'REGISTRY OF MEMBERS — field mapping, decode legend & assumptions');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

                $sheet->setCellValue('A3', 'AS-OF DATE (drives the AGE column):');
                $sheet->setCellValue('B3', ExcelDate::PHPToExcel(Registry::asOfDate()));
                $sheet->getStyle('B3')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                $sheet->getStyle('B3')->getFont()->setBold(true);
                $sheet->getStyle('B3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                $sheet->setCellValue('A4', 'Edit B3 and recalculate to re-age every row.');

                $lines = [
                    ['', ''],
                    ['SOURCE', 'Result sets of database/sql/cic_merged_registry_extract.sql'],
                    ['Members result set', 'One row per member (CIC "ID" subject data). Import as "Members".'],
                    ['Loans result set', 'One row per loan account (CIC "CI" contract data). Import as "Loans".'],
                    ['', ''],
                    ['FIELD MAPPING', 'Registry column  ←  source'],
                    ['A–D  Name', 'CIC: Last / First / Middle / Suffix'],
                    ['E  Membership Number (CID)', 'CIC: Provider Subject No'],
                    ['F  TIN', 'Manual — no source in the CIC extract'],
                    ['G  Date Accepted', 'Seeded from the "(D/A m/d/yy)" token in the address free-text; best-effort, VERIFY against BOD records. Otherwise manual.'],
                    ['H–I  BOD Resolution', 'Manual'],
                    ['J–M  Type / Kind / MIGS / Active', 'Manual'],
                    ['N–P  Initial Capital Subscription', 'Manual (from the share-capital subsidiary ledger)'],
                    ['Q  Present Address', 'Seeded from CIC Address 1 (full text). Editable.'],
                    ['R–S  Permanent / Business Address', 'Manual'],
                    ['T  Date of Birth', 'CIC: Date of Birth'],
                    ['U  Age', 'Formula: DATEDIF(DOB, Notes!B3, "Y")'],
                    ['V  Sex assigned at birth', 'Seeded from CIC Gender (M→Male, F→Female). Editable.'],
                    ['W  Gender', 'Seeded from CIC Gender; adjust manually where SOGIE data exists.'],
                    ['X  Civil Status', 'Seeded from CIC Civil Status code (00M→Married, 00S→Single, 00W→Widowed…). Editable.'],
                    ['Y  Educational Attainment', 'Manual'],
                    ['Z–AC  Occupation block', 'Manual (CIC extract carries no employment data)'],
                    ['AD  Number of Dependents', 'Manual'],
                    ['AE  Religion / Social Affiliation', 'Manual'],
                    ['AF  Annual Income', 'Manual'],
                    ['AG–AH  Termination of Membership', 'Manual'],
                    ['AI  Ethnicity', 'Manual'],
                    ['AJ–AK  PWD / Disability', 'Manual'],
                    ['AL  Contact Number', 'Seeded from CIC mobile contact (leading 0 restored). Editable.'],
                    ['AM  Email Address', 'Seeded from CIC email contact. Editable.'],
                    ['AN–AO  Share Capital / Savings Balance', 'Manual (from subsidiary ledgers)'],
                    ['AP–AS  Loan Portfolio Summary', 'Computed from the imported CI contracts, aggregated by CID.'],
                    ['', ''],
                    ['ASSUMPTIONS', ''],
                    ['1', 'Names kept in the ALL-CAPS form used by the source system.'],
                    ['2', 'Re-importing the Members file refreshes only the source columns; every hand-keyed value is preserved.'],
                    ['3', '"Date Accepted" is text-extracted and must be validated before any CDA submission.'],
                    ['4', 'Loan amounts are reproduced exactly as stored in the CIC "CI" export — confirm the unit with the query owner.'],
                ];

                $r = 6;
                foreach ($lines as [$a, $b]) {
                    $sheet->setCellValue("A{$r}", $a);
                    $sheet->setCellValue("B{$r}", $b);
                    $sheet->getStyle("A{$r}")->getFont()->setBold($b === '' && $a !== '');
                    $sheet->getStyle("A{$r}:B{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
                    $r++;
                }

                $sheet->getColumnDimension('A')->setWidth(34);
                $sheet->getColumnDimension('B')->setWidth(95);
                $sheet->setShowGridlines(false);
            },
        ];
    }
}
