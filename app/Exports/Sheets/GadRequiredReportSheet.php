<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * GAD REQUIRED REPORT — members' contribution grid, split by sex.
 * Body numbers are computed from the members collection; the Total row uses
 * SUM formulas (template convention).
 */
class GadRequiredReportSheet implements FromCollection, WithEvents, WithTitle
{
    public function __construct(protected Collection $members) {}

    public function collection(): Collection
    {
        return collect();
    }

    public function title(): string
    {
        return 'GAD REQUIRED REPORT';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $male = $this->members->filter(fn ($m) => $m->sex_assigned_at_birth === 'Male');
                $female = $this->members->filter(fn ($m) => $m->sex_assigned_at_birth === 'Female');

                $rows = [
                    'Members contributing to Share Capital' => [
                        fn ($c) => $c->filter(fn ($m) => (float) $m->share_capital_balance > 0),
                        fn ($m) => (float) $m->share_capital_balance,
                    ],
                    'Members contributing to savings deposit' => [
                        fn ($c) => $c->filter(fn ($m) => (float) $m->savings_balance > 0),
                        fn ($m) => (float) $m->savings_balance,
                    ],
                    'Members contributing to special savings deposit' => [fn ($c) => collect(), fn ($m) => 0],
                    'Members contributing to insurance' => [fn ($c) => collect(), fn ($m) => 0],
                    'Members contributing to Loan Portfolio' => [
                        fn ($c) => $c->filter(fn ($m) => (int) $m->loan_count > 0),
                        fn ($m) => (float) $m->total_outstanding,
                    ],
                    'Members contributing to Delinquency' => [
                        fn ($c) => $c->filter(fn ($m) => (int) $m->delinquent_loan_count > 0),
                        fn ($m) => (float) $m->total_overdue_amount,
                    ],
                ];

                $sheet->setCellValue('A2', 'MEMBERS CONTRIBUTION - AS OF THE MONTH');
                $sheet->setCellValue('A3', 'MEMBERS');
                $sheet->setCellValue('B3', 'MALE');
                $sheet->setCellValue('E3', 'FEMALE');
                $sheet->setCellValue('H3', 'TOTAL');
                foreach ([['B4', 'Number'], ['C4', '%'], ['D4', 'Amount'], ['E4', 'Number'], ['F4', '%'], ['G4', 'Amount'], ['H4', 'TOTAL Number'], ['I4', '%']] as [$cell, $label]) {
                    $sheet->setCellValue($cell, $label);
                }

                $maleTotal = max($male->count(), 1);
                $femaleTotal = max($female->count(), 1);
                $grandTotal = max($this->members->count(), 1);

                $r = 5;
                foreach ($rows as $label => [$filter, $amount]) {
                    $mSet = $filter($male);
                    $fSet = $filter($female);
                    $allSet = $filter($this->members);

                    $sheet->setCellValue("A{$r}", $label);
                    $sheet->setCellValue("B{$r}", $mSet->count());
                    $sheet->setCellValue("C{$r}", $mSet->count() / $maleTotal);
                    $sheet->setCellValue("D{$r}", $mSet->sum($amount));
                    $sheet->setCellValue("E{$r}", $fSet->count());
                    $sheet->setCellValue("F{$r}", $fSet->count() / $femaleTotal);
                    $sheet->setCellValue("G{$r}", $fSet->sum($amount));
                    $sheet->setCellValue("H{$r}", $allSet->count());
                    $sheet->setCellValue("I{$r}", $allSet->count() / $grandTotal);
                    $r++;
                }

                $sheet->setCellValue("A{$r}", 'Total');
                foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] as $col) {
                    $sheet->setCellValue("{$col}{$r}", "=SUM({$col}5:{$col}".($r - 1).')');
                }

                foreach (['C', 'F', 'I'] as $col) {
                    $sheet->getStyle("{$col}5:{$col}{$r}")->getNumberFormat()->setFormatCode('0.0%');
                }
                foreach (['D', 'G'] as $col) {
                    $sheet->getStyle("{$col}5:{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                }

                foreach (['A3:A4', 'B3:D3', 'E3:G3', 'H3:I3', 'A2:I2'] as $range) {
                    $sheet->mergeCells($range);
                }
                $sheet->getStyle("A2:I{$r}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle('A2:I4')->getFont()->setBold(true);
                $sheet->getStyle("A{$r}:I{$r}")->getFont()->setBold(true);
                $sheet->getColumnDimension('A')->setWidth(46);
                foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] as $col) {
                    $sheet->getColumnDimension($col)->setWidth(13);
                }
            },
        ];
    }
}
