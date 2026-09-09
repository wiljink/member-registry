<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberLoan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_period' => 'date',
            'start_date' => 'date',
            'planned_end_date' => 'date',
            'actual_end_date' => 'date',
            'last_payment_date' => 'date',
            'financed_amount' => 'decimal:2',
            'monthly_payment' => 'decimal:2',
            'last_payment_amount' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'overdue_amount' => 'decimal:2',
            'guarantors' => 'array',
            'linked_subjects' => 'array',
            'raw' => 'array',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'cid', 'cid');
    }
}
