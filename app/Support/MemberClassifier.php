<?php

namespace App\Support;

use App\Models\Member;
use Illuminate\Support\Carbon;

/**
 * Derives the four "membership upon acceptance" dropdowns from the member's
 * financial standing. Rules (confirmed with ORO):
 *
 *   KIND  : (share capital + savings) >= threshold -> Full-fledged, else Non Full-fledged
 *   TYPE  : same threshold                          -> Regular,      else Associate
 *   MIGS  : not delinquent on any loan              -> MIGS,         else Non-MIGS
 *           (a member with no loan is not delinquent -> MIGS)
 *   ACTIVE: has savings OR share activity, AND (no loan OR loan current for 12 months)
 *
 * "Activity" = a transaction in the trailing 12 months; when the extract carries
 * no transaction dates, a positive balance is used as the proxy.
 *
 * Each field auto-fills on import but a hand-edit locks it (see classification_locked).
 */
class MemberClassifier
{
    /** @var array<int,string> the fields this service manages, in registry order */
    public const FIELDS = ['membership_type', 'membership_kind', 'migs_status', 'activity_status'];

    public static function threshold(): float
    {
        return (float) config('registry.classification.share_savings_threshold', 3000);
    }

    /**
     * @return array{membership_type: ?string, membership_kind: ?string, migs_status: ?string, activity_status: ?string}
     */
    public static function compute(Member $m): array
    {
        $share = $m->share_capital_balance;
        $savings = $m->savings_balance;
        $hasBalanceData = ! is_null($share) || ! is_null($savings);
        $combined = (float) ($share ?? 0) + (float) ($savings ?? 0);
        $meetsThreshold = $hasBalanceData ? $combined >= self::threshold() : null;

        // MIGS — no loan means not delinquent, which means MIGS.
        $migs = (int) $m->delinquent_loan_count > 0 ? 'Non-MIGS' : 'MIGS';

        // ACTIVE
        $window = ($m->data_period ? $m->data_period->copy() : Carbon::now())->subMonths(12);
        $savingsActive = $m->last_savings_txn_date
            ? $m->last_savings_txn_date->gte($window)
            : (float) ($savings ?? 0) > 0;
        $shareActive = $m->last_share_txn_date
            ? $m->last_share_txn_date->gte($window)
            : (float) ($share ?? 0) > 0;
        $hasActivityData = $hasBalanceData
            || ! is_null($m->last_savings_txn_date)
            || ! is_null($m->last_share_txn_date);

        $loanOk = (int) $m->loan_count === 0
            || ((int) $m->loan_count > 0 && (int) $m->overdue_installments_12mo === 0);

        $active = $hasActivityData
            ? (($savingsActive || $shareActive) && $loanOk ? 'Active' : 'Inactive')
            : null;

        return [
            'membership_type' => is_null($meetsThreshold) ? null : ($meetsThreshold ? 'Regular' : 'Associate'),
            'membership_kind' => is_null($meetsThreshold) ? null : ($meetsThreshold ? 'Full-fledged' : 'Non Full-fledged'),
            'migs_status' => $migs,
            'activity_status' => $active,
        ];
    }

    /**
     * Fill each managed field with its computed value, skipping locked fields
     * and fields the rule cannot compute yet (inputs missing).
     */
    public static function apply(Member $m): void
    {
        $locked = (array) $m->classification_locked;
        foreach (self::compute($m) as $field => $value) {
            if ($value !== null && ! in_array($field, $locked, true)) {
                $m->{$field} = $value;
            }
        }
    }

    /**
     * After a manual save, mark a field as locked when the user's value differs
     * from what the rule currently produces (and unlock it when they match again).
     */
    public static function reconcileLocks(Member $m): void
    {
        $computed = self::compute($m);
        $locked = [];
        foreach (self::FIELDS as $field) {
            $userValue = $m->{$field};
            if (filled($userValue) && $computed[$field] !== null && $userValue !== $computed[$field]) {
                $locked[] = $field;
            }
        }
        $m->classification_locked = $locked ?: null;
    }
}
