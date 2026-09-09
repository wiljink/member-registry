<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\Member;

class DashboardController extends Controller
{
    public function index()
    {
        $byStatus = Member::query()
            ->selectRaw('completion_status, COUNT(*) as total')
            ->groupBy('completion_status')
            ->pluck('total', 'completion_status');

        $stats = [
            'total' => (int) $byStatus->sum(),
            'complete' => (int) ($byStatus['complete'] ?? 0),
            'in_progress' => (int) ($byStatus['in_progress'] ?? 0),
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'with_loans' => Member::where('loan_count', '>', 0)->count(),
            'delinquent' => Member::where('delinquent_loan_count', '>', 0)->count(),
            'male' => Member::where('sex_assigned_at_birth', 'Male')->count(),
            'female' => Member::where('sex_assigned_at_birth', 'Female')->count(),
        ];

        $batches = ImportBatch::latest()->limit(10)->get();

        $byBranch = Member::query()
            ->selectRaw("COALESCE(branch, '(none)') as branch")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN completion_status = 'complete' THEN 1 ELSE 0 END) as complete")
            ->groupBy('branch')
            ->orderByDesc('total')
            ->get();

        $branches = Member::branchNames();
        $periods = Member::dataPeriods();

        return view('dashboard', compact('stats', 'batches', 'byBranch', 'branches', 'periods'));
    }
}
