<?php

namespace App\Console\Commands;

use App\Services\RegistryImporter;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class ImportRegistryFile extends Command
{
    protected $signature = 'app:import-file {type : members|loans} {path : path to the .xlsx/.csv file}';

    protected $description = 'Import a members or loans extract from the command line';

    public function handle(RegistryImporter $importer): int
    {
        $type = $this->argument('type');
        $path = $this->argument('path');

        if (! in_array($type, ['members', 'loans'], true)) {
            $this->error('type must be "members" or "loans"');

            return self::FAILURE;
        }
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $batch = $importer->run(
            new UploadedFile($path, basename($path), null, null, true),
            $type,
        );

        $this->table(
            ['status', 'total', 'created', 'updated', 'skipped'],
            [[$batch->status, $batch->rows_total, $batch->rows_created, $batch->rows_updated, $batch->rows_skipped]],
        );

        foreach ((array) $batch->errors as $error) {
            $this->warn($error);
        }

        return $batch->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
