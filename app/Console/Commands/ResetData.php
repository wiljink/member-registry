<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\MemberLoan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetData extends Command
{
    protected $signature = 'app:reset-data {--force : skip the confirmation prompt}';

    protected $description = 'Wipe all imported/registry data. Keeps users, branches and settings.';

    public function handle(): int
    {
        $counts = [
            'members' => Member::count(),
            'member_loans' => MemberLoan::count(),
            'import_batches' => ImportBatch::count(),
        ];

        $this->table(['table', 'rows to delete'], collect($counts)->map(fn ($n, $t) => [$t, number_format($n)])->values());
        $this->line('Kept intact: users, sessions/cache/jobs, config.');

        if (! $this->option('force') && ! $this->confirm('Delete the rows above?', false)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();
        foreach (['member_loans', 'import_batches', 'members'] as $table) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();

        $this->info('Done. Members, loans and import history cleared.');

        return self::SUCCESS;
    }
}
