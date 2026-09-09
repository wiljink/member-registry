<?php

namespace App\Console\Commands;

use App\Services\RegistryImporter;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class ImportRegistryFile extends Command
{
    protected $signature = 'app:import-file
        {type : members|loans}
        {path : path to the .xlsx/.csv file}
        {--period= : data month as YYYY-MM (default: current month)}
        {--branch= : optional override — force every row to this branch (default: use each row\'s Branch Code)}';

    protected $description = 'Import a members or loans extract from the command line';

    public function handle(RegistryImporter $importer): int
    {
        $type = $this->argument('type');
        $path = $this->argument('path');
        $branch = $this->option('branch');
        $period = $this->option('period') ?: now()->format('Y-m');

        if (! in_array($type, ['members', 'loans'], true)) {
            $this->error('type must be "members" or "loans"');

            return self::FAILURE;
        }
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('--period must be YYYY-MM');

            return self::FAILURE;
        }
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $batch = $importer->run(
            new UploadedFile($path, basename($path), null, null, true),
            $type,
            $period,
            $branch,
        );

        $this->table(
            ['status', 'month', 'total', 'created', 'updated', 'skipped'],
            [[$batch->status, $period, $batch->rows_total, $batch->rows_created, $batch->rows_updated, $batch->rows_skipped]],
        );

        foreach ((array) $batch->errors as $error) {
            $this->warn($error);
        }

        return $batch->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
