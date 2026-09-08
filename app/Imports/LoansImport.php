<?php

namespace App\Imports;

use App\Models\MemberLoan;
use App\Support\Registry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Imports the "Loans" result set of cic_merged_registry_extract.sql
 * (identical column headers to the sample "SQL ID output.xlsx").
 */
class LoansImport implements ToCollection, WithChunkReading, WithHeadingRow, SkipsEmptyRows
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var array<int,string> */
    public array $errors = [];

    /** @var array<string,bool> */
    public array $touchedCids = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $d = $this->normalize($row->toArray());
            $cid = $this->pick($d, ['provider subject no', 'cid']);
            $contract = $this->pick($d, ['provider contract no', 'contract no']);

            if (! filled($cid) || ! filled($contract)) {
                $this->skipped++;

                continue;
            }

            try {
                $loan = MemberLoan::firstOrNew(['cid' => (string) $cid, 'contract_no' => (string) $contract]);
                $isNew = ! $loan->exists;

                $loan->fill([
                    'role'                   => $this->pick($d, ['role']),
                    'contract_type'          => $this->pick($d, ['contract type']),
                    'contract_phase'         => $this->pick($d, ['contract phase']),
                    'currency'               => $this->pick($d, ['currency']),
                    'start_date'             => $this->date($this->pick($d, ['contract start date'])),
                    'planned_end_date'       => $this->date($this->pick($d, ['contract end planned date'])),
                    'actual_end_date'        => $this->date($this->pick($d, ['contract end actual date'])),
                    'last_payment_date'      => $this->date($this->pick($d, ['last payment date'])),
                    'financed_amount'        => $this->num($this->pick($d, ['financed amount'])),
                    'monthly_payment'        => $this->num($this->pick($d, ['monthly payment amount'])),
                    'last_payment_amount'    => $this->num($this->pick($d, ['last payment amount'])),
                    'outstanding_balance'    => $this->num($this->pick($d, ['outstanding balance'])),
                    'overdue_amount'         => $this->num($this->pick($d, ['overdue payments amount'])),
                    'installments_number'    => $this->int($this->pick($d, ['installments number'])),
                    'outstanding_payments_no' => $this->int($this->pick($d, ['outstanding payments number'])),
                    'overdue_payments_no'    => $this->int($this->pick($d, ['overdue payments number'])),
                    'overdue_days'           => $this->int($this->pick($d, ['overdue days'])),
                    'purpose_of_credit'      => $this->pick($d, ['purpose of credit']),
                    'guarantors'             => $this->collectNames($d, 'guarantor name '),
                    'linked_subjects'        => $this->collectNames($d, 'name of the linked subject '),
                    'raw'                    => $this->rawRow($row->toArray()),
                ]);
                $loan->save();

                $this->touchedCids[(string) $cid] = true;
                $isNew ? $this->created++ : $this->updated++;
            } catch (\Throwable $e) {
                $this->skipped++;
                if (count($this->errors) < 50) {
                    $this->errors[] = "CID {$cid} / {$contract}: {$e->getMessage()}";
                }
            }
        }
    }

    /* --------------------------------------------------------------- */

    protected function normalize(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            $out[preg_replace('/[^a-z0-9]/', '', strtolower((string) $key))] = $value;
        }

        return $out;
    }

    protected function pick(array $normalized, array $candidates): mixed
    {
        foreach ($candidates as $candidate) {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower($candidate));
            if (array_key_exists($key, $normalized)) {
                return Registry::clean($normalized[$key]);
            }
        }

        return null;
    }

    protected function collectNames(array $normalized, string $prefix): array
    {
        $names = [];
        for ($n = 1; $n <= 6; $n++) {
            $value = $this->pick($normalized, [$prefix.$n]);
            if (filled($value)) {
                $names[] = $value;
            }
        }

        return $names;
    }

    protected function rawRow(array $row): array
    {
        return collect($row)
            ->reject(fn ($v) => Registry::clean($v) === null)
            ->all();
    }

    protected function num($value): ?float
    {
        $value = Registry::clean($value);

        return is_numeric($value) ? (float) $value : null;
    }

    protected function int($value): ?int
    {
        $value = Registry::clean($value);

        return is_numeric($value) ? (int) $value : null;
    }

    protected function date($value): ?string
    {
        $value = Registry::clean($value);
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                if ((int) $value > 19000000 && (int) $value < 99999999) {
                    return CarbonImmutable::createFromFormat('Ymd', (string) (int) $value)->toDateString();
                }

                return CarbonImmutable::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                )->toDateString();
            }

            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
