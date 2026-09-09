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
                foreach (range('A', 'U') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
