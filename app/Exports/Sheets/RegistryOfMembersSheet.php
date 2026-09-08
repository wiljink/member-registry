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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RegistryOfMembersSheet implements FromCollection, WithTitle, WithEvents
{
    public function __construct(protected Collection $members) {}

    public function collection(): Collection
    {
        return collect();
    }

    public function title(): string
    {
        return 'REGISTRY OF MEMBERS';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $columns = Registry::columns();
                $last = Registry::LAST_COLUMN;
                $firstRow = Registry::FIRST_DATA_ROW;

                $this->titleBlock($sheet);
                $this->headerTiers($sheet, $columns);
                $this->applyMerges($sheet);
                $this->styleHeader($sheet, $last);
                $lastRow = $this->writeData($sheet, $columns, $firstRow);
                $this->numberFormats($sheet, $columns, $firstRow, $lastRow);
                $this->finish($sheet, $last, $lastRow);
            },
        ];
    }

    protected function titleBlock(Worksheet $sheet): void
    {
        $count = $this->members->count();
        $asOf = Registry::asOfDate()->format('Y-m-d');

        $sheet->setCellValue('A1', 'REGISTRY OF MEMBERS');
        $sheet->setCellValue('A2', 'CDA REPORT');
        $sheet->setCellValue('A3', "ORO Integrated Cooperative   |   Source: CIC SQL export   |   Members: {$count}   |   Generated {$asOf}");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(8)->getColor()->setARGB('FF555555');
    }

    protected function headerTiers(Worksheet $sheet, array $columns): void
    {
        foreach ($columns as $c) {
            if (! is_null($c['t4'])) {
                $sheet->setCellValue("{$c['col']}4", $c['t4']);
            }
            if (! is_null($c['t5'])) {
                $sheet->setCellValue("{$c['col']}5", $c['t5']);
            }
            if (! is_null($c['t6'])) {
                $sheet->setCellValue("{$c['col']}6", $c['t6']);
            }
        }
    }

    protected function applyMerges(Worksheet $sheet): void
    {
        foreach (Registry::mergeRanges() as $range) {
            $sheet->mergeCells($range);
        }
    }

    protected function styleHeader(Worksheet $sheet, string $last): void
    {
        $sheet->getStyle("A4:{$last}4")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E78']],
        ]);
        $sheet->getStyle("A5:{$last}6")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8, 'color' => ['argb' => 'FF1F4E78']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9E2F3']],
        ]);
        $sheet->getStyle("A4:{$last}6")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFB0B0B0']]],
        ]);

        $sheet->getRowDimension(4)->setRowHeight(46);
        $sheet->getRowDimension(5)->setRowHeight(60);
        $sheet->getRowDimension(6)->setRowHeight(46);
    }

    protected function writeData(Worksheet $sheet, array $columns, int $firstRow): int
    {
        if ($this->members->isEmpty()) {
            return $firstRow - 1;
        }

        $matrix = [];
        $rowNumber = $firstRow;

        foreach ($this->members as $member) {
            $line = [];
            foreach ($columns as $c) {
                $line[] = $this->cellValue($c, $member, $rowNumber);
            }
            $matrix[] = $line;
            $rowNumber++;
        }

        $sheet->fromArray($matrix, null, 'A'.$firstRow, true);

        return $rowNumber - 1;
    }

    protected function cellValue(array $c, $member, int $row): mixed
    {
        if (isset($c['formula'])) {
            return ($c['formula'])($row);
        }
        if (isset($c['value'])) {
            return ($c['value'])($member);
        }
        if (empty($c['field'])) {
            return null;
        }

        $value = $member->{$c['field']};
        if (blank($value) && $value !== 0 && $value !== '0') {
            return null;
        }

        if (! empty($c['date'])) {
            try {
                return ExcelDate::PHPToExcel($value instanceof \DateTimeInterface ? $value : \Carbon\Carbon::parse($value));
            } catch (\Throwable) {
                return null;
            }
        }

        if (! empty($c['money'])) {
            return (float) $value;
        }

        return is_scalar($value) ? $value : (string) $value;
    }

    protected function numberFormats(Worksheet $sheet, array $columns, int $firstRow, int $lastRow): void
    {
        if ($lastRow < $firstRow) {
            return;
        }

        foreach ($columns as $c) {
            $range = "{$c['col']}{$firstRow}:{$c['col']}{$lastRow}";
            if (! empty($c['date'])) {
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode('mm/dd/yyyy');
            } elseif (! empty($c['money'])) {
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['size' => 9],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => in_array($c['col'], ['A', 'B', 'C', 'Q', 'R', 'S'], true)],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
            ]);
        }
    }

    protected function finish(Worksheet $sheet, string $last, int $lastRow): void
    {
        foreach (Registry::columns() as $c) {
            $sheet->getColumnDimension($c['col'])->setWidth($c['width'] ?? 14);
        }

        $sheet->freezePane('E'.Registry::FIRST_DATA_ROW);
        $sheet->setAutoFilter("A6:{$last}".max($lastRow, 6));
        $sheet->setShowGridlines(false);
        $sheet->setSelectedCell('A7');
    }
}
