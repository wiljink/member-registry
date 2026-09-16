<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CiContractsSheet implements FromCollection, WithEvents, WithTitle
{
    public function __construct(protected Collection $loans) {}

    public function collection(): Collection
    {
        return collect();
    }

    public function title(): string
    {
        return 'CI Contracts';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $headers = [
                    'Provider Subject No (CID)', 'Provider Contract No', 'Role', 'Contract Type',
                    'Contract Phase', 'Currency', 'Start Date', 'Planned End Date', 'Actual End Date',
                    'Last Payment Date', 'Financed Amount', 'Monthly Payment', 'Last Payment Amount',
                    'Outstanding Balance', 'Past-Due Amount', 'Installments', 'Outstanding Payments',
                    'Overdue Payments', 'Overdue Days', 'Purpose', 'Guarantors',
                ];
                $sheet->fromArray($headers, null, 'A1');

                $rows = [];
                foreach ($this->loans as $l) {
                    $rows[] = [
                        $l->cid, $l->contract_no, $l->role, $l->contract_type, $l->contract_phase,
                        $l->currency,
                        optional($l->start_date)->format('m/d/Y'),
                        optional($l->planned_end_date)->format('m/d/Y'),
                        optional($l->actual_end_date)->format('m/d/Y'),
                        optional($l->last_payment_date)->format('m/d/Y'),
                        $l->financed_amount, $l->monthly_payment, $l->last_payment_amount,
                        $l->outstanding_balance, $l->overdue_amount, $l->installments_number,
                        $l->outstanding_payments_no, $l->overdue_payments_no, $l->overdue_days,
                        $l->purpose_of_credit, implode('; ', $l->guarantors ?? []),
                    ];
                }
                if ($rows) {
                    $sheet->fromArray($rows, null, 'A2', true);
                    $lastRow = count($rows) + 1;
                    foreach (['K', 'L', 'M', 'N', 'O'] as $col) {
                        $sheet->getStyle("{$col}2:{$col}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                $sheet->getStyle('A1:U1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E78']],
                    'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->freezePane('A2');

                // Fixed widths instead of setAutoSize(true): autosize makes PhpSpreadsheet
                // measure every cell in every column, which is a major memory/CPU hit once
                // this sheet holds thousands of loan rows (all branches, no filter).
                $widths = [
                    'A' => 20, 'B' => 20, 'C' => 10, 'D' => 16, 'E' => 14, 'F' => 10,
                    'G' => 12, 'H' => 14, 'I' => 14, 'J' => 14, 'K' => 15, 'L' => 15,
                    'M' => 16, 'N' => 16, 'O' => 14, 'P' => 12, 'Q' => 14, 'R' => 14,
                    'S' => 12, 'T' => 30, 'U' => 30,
                ];
                foreach ($widths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}
