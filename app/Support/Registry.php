<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Single source of truth for:
 *   - decoding raw CIC codes into readable labels,
 *   - parsing the "(D/A m/d/yy)" registration token out of the address free-text,
 *   - the exact column layout / header tiers / merge ranges of the CDA
 *     "REGISTRY OF MEMBERS" template.
 *
 * Used by both the import pipeline and the Excel export so the two never drift.
 */
class Registry
{
    /* ------------------------------------------------------------------ *
     |  Code decoding
     * ------------------------------------------------------------------ */

    public static function genderLabel(?string $raw): ?string
    {
        return match (strtoupper(trim((string) $raw))) {
            'M', '001' => 'Male',
            'F', '002' => 'Female',
            default => null,
        };
    }

    public static function civilStatusLabel(?string $raw): ?string
    {
        $code = strtoupper(trim((string) $raw));

        return match ($code) {
            '00M', 'M00', 'M' => 'Married',
            '00S', 'S00', 'S' => 'Single',
            '00W', 'W00', 'W' => 'Widowed',
            '00A', 'A00' => 'Annulled',
            '00L', 'L00' => 'Legally Separated',
            '00C', 'C00' => 'Cohabiting',
            default => null,
        };
    }

    /**
     * Decode the CIC "Occupation Category" (T_CIF.CIFCode2 / USERLOOKUP 62) into
     * one of the member form's occupation_category values. Accepts either the raw
     * numeric code ("001".."007") or the extract's already-decoded label.
     * Returns null for "Other"/unknown/blank, or for anything the config list no
     * longer offers.
     */
    public static function occupationCategoryLabel(?string $raw): ?string
    {
        $key = strtolower(trim((string) $raw));
        // Excel drops the leading zeros on the CIC code column ("002" -> "2"),
        // so normalise "2" / "02" / "002" to a bare digit before matching.
        if (preg_match('/^0*([1-9]\d*)$/', $key, $m)) {
            $key = $m[1];
        }

        $label = match ($key) {
            '1', 'private', 'private employee' => 'Private employee',
            '2', 'government', 'government employee' => 'Government employee',
            '3', 'self-employed', 'self employed' => 'Self-employed',
            '4', 'pensioner', 'retired' => 'Retired',
            '5', 'student' => 'Student',
            '6', 'farmer/fisherfolk', 'farmer / fisherfolk', 'farmer', 'fisherfolk' => 'Farmer / Fisherfolk',
            default => null, // '7' / 'other' / '' / unrecognised
        };

        return ($label && in_array($label, self::occupationCategories(), true)) ? $label : null;
    }

    /**
     * Pull a registration date out of strings like:
     *   "POBLACION, DAUIS, BOHOL (D/A 1/21/2011)"
     *   "... D/A 12/29/18"
     *   "... (DA 06/16/11)"
     * Returns a CarbonImmutable or null. Best-effort only — must be verified.
     */
    public static function parseDateAccepted(?string $address): ?CarbonImmutable
    {
        if (! $address) {
            return null;
        }

        if (! preg_match('/D\s*\/?\s*A\b[:\s]*?(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/i', $address, $m)) {
            return null;
        }

        [$month, $day, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($year < 100) {
            $year += 2000;
        }

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || $year < 1990 || $year > 2100) {
            return null;
        }

        try {
            return CarbonImmutable::createFromDate($year, $month, $day)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Restore the leading zero on a bare 10-digit PH mobile number (9XXXXXXXXX).
     */
    public static function formatMobile($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || strtoupper($value) === 'NULL') {
            return null;
        }

        if (preg_match('/^9\d{9}$/', $value)) {
            return '0'.$value;
        }

        return $value;
    }

    /**
     * Normalise a raw cell value coming from the SQL export.
     * The staging queries emit the literal string "NULL" for empty columns.
     */
    public static function clean($value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);

            return ($trimmed === '' || strtoupper($trimmed) === 'NULL') ? null : $trimmed;
        }

        return $value;
    }

    /* ------------------------------------------------------------------ *
     |  As-of date
     * ------------------------------------------------------------------ */

    /**
     * @param  string|null  $period  a "YYYY-MM" data month; when given (and no explicit
     *                               config override is set) the as-of date is that month's
     *                               last day, so AGE and balances line up with the data.
     */
    public static function asOfDate(?string $period = null): CarbonImmutable
    {
        if ($configured = config('registry.as_of_date')) {
            return CarbonImmutable::parse($configured)->startOfDay();
        }

        if ($period) {
            return CarbonImmutable::createFromFormat('Y-m', $period)->endOfMonth()->startOfDay();
        }

        return CarbonImmutable::now()->startOfDay();
    }

    /** First day of a "YYYY-MM" month, for storing / comparing periods. */
    public static function periodToDate(?string $period): ?CarbonImmutable
    {
        if (! $period) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    /* ------------------------------------------------------------------ *
     |  Dropdown option lists
     * ------------------------------------------------------------------ */

    /**
     * Stored values for the "MAIN CATEGORIES" occupation column, in order.
     * Single source shared by the member form, the request validation, the
     * exported column-Z header text and its Excel data-validation dropdown.
     *
     * @return list<string>
     */
    public static function occupationCategories(): array
    {
        return array_keys((array) config('registry.options.occupation_category', []));
    }

    /* ------------------------------------------------------------------ *
     |  Registry template layout
     * ------------------------------------------------------------------ */

    /**
     * Ordered list of every column in the CDA registry, A..AS.
     *
     * Each entry:
     *   col     - Excel column letter
     *   t4/t5/t6- the three header tiers (null where a merge spans down)
     *   field   - Member attribute to read (null for computed/blank columns)
     *   formula - closure(int $row): string  -> writes an Excel formula instead
     *   width   - column width
     *   money   - true to apply a #,##0.00 number format
     *   date    - true to apply a mm/dd/yyyy number format
     */
    public static function columns(): array
    {
        return [
            ['col' => 'A',  't4' => 'NAME OF MEMBER', 't5' => 'LAST NAME',  't6' => null, 'field' => 'last_name',   'width' => 20],
            ['col' => 'B',  't4' => null, 't5' => 'FIRST NAME',  't6' => null, 'field' => 'first_name',  'width' => 18],
            ['col' => 'C',  't4' => null, 't5' => 'MIDDLE NAME', 't6' => null, 'field' => 'middle_name', 'width' => 18],
            ['col' => 'D',  't4' => null, 't5' => "SUFFIX\n(I, II, III, IV, Jr., Sr., etc.)", 't6' => null, 'field' => 'suffix', 'width' => 12],

            ['col' => 'E',  't4' => "MEMBERSHIP NUMBER\n(CID)", 't5' => null, 't6' => null, 'field' => 'cid', 'width' => 12],
            ['col' => 'F',  't4' => "TAX IDENTIFICATION NUMBER\n(TIN)", 't5' => null, 't6' => null, 'field' => 'tin', 'width' => 18],

            ['col' => 'G',  't4' => 'INFORMATION ON MEMBERSHIP UPON ACCEPTANCE', 't5' => "DATE ACCEPTED\n(Registration Date)", 't6' => null, 'field' => 'date_accepted', 'width' => 14, 'date' => true],
            ['col' => 'H',  't4' => null, 't5' => 'BOD RESOLUTION NUMBER', 't6' => 'Date Confirmed as Newly Accepted Member', 'field' => 'bod_res_date_confirmed', 'width' => 16, 'date' => true],
            ['col' => 'I',  't4' => null, 't5' => null, 't6' => 'Resolution Number', 'field' => 'bod_res_number', 'width' => 14],
            ['col' => 'J',  't4' => null, 't5' => 'TYPE/KIND OF MEMBERSHIP', 't6' => "TYPE OF MEMBERSHIP\n(Regular / Associate)", 'field' => 'membership_type', 'width' => 16],
            ['col' => 'K',  't4' => null, 't5' => null, 't6' => "KIND OF MEMBERSHIP\n(Full-fledged / Non Full-fledged)", 'field' => 'membership_kind', 'width' => 18],
            ['col' => 'L',  't4' => null, 't5' => null, 't6' => 'MIGS / NON-MIGS', 'field' => 'migs_status', 'width' => 14],
            ['col' => 'M',  't4' => null, 't5' => null, 't6' => 'ACTIVE / INACTIVE', 'field' => 'activity_status', 'width' => 14],
            ['col' => 'N',  't4' => null, 't5' => "INITIAL CAPITAL SUBSCRIPTION\n(Opening Balance – Share only)", 't6' => 'Number of Shares', 'field' => 'initial_shares', 'width' => 12],
            ['col' => 'O',  't4' => null, 't5' => null, 't6' => 'Amount', 'field' => 'initial_share_amount', 'width' => 12, 'money' => true],
            ['col' => 'P',  't4' => null, 't5' => null, 't6' => 'Initial Paid-Up', 'field' => 'initial_paid_up', 'width' => 12, 'money' => true],

            ['col' => 'Q',  't4' => "MEMBER'S PROFILE", 't5' => 'ADDRESS', 't6' => 'Present Address', 'field' => 'present_address', 'width' => 42],
            ['col' => 'R',  't4' => null, 't5' => null, 't6' => 'Permanent Address', 'field' => 'permanent_address', 'width' => 28],
            ['col' => 'S',  't4' => null, 't5' => null, 't6' => 'Business Address', 'field' => 'business_address', 'width' => 22],
            ['col' => 'T',  't4' => null, 't5' => 'DATE OF BIRTH', 't6' => null, 'field' => 'birth_date', 'width' => 12, 'date' => true],
            ['col' => 'U',  't4' => null, 't5' => 'AGE', 't6' => null, 'field' => null, 'width' => 7,
                'formula' => fn (int $row) => "=IF(\$T{$row}=\"\",\"\",DATEDIF(\$T{$row},'Notes'!\$B\$3,\"Y\"))"],
            ['col' => 'V',  't4' => null, 't5' => "SEX ASSIGNED AT BIRTH\n(Male / Female / Intersex)", 't6' => null, 'field' => 'sex_assigned_at_birth', 'width' => 14],
            ['col' => 'W',  't4' => null, 't5' => "GENDER\n(Male, Female, LGBTQIA++, Prefer not to say)", 't6' => null, 'field' => 'gender_identity', 'width' => 16],
            ['col' => 'X',  't4' => null, 't5' => "CIVIL STATUS\n(Married / Single / Widowed / Legally Separated / Annulled / Cohabiting)", 't6' => null, 'field' => 'civil_status', 'width' => 20],
            ['col' => 'Y',  't4' => null, 't5' => 'HIGHEST EDUCATIONAL ATTAINMENT', 't6' => null, 'field' => 'education_attainment', 'width' => 18],
            ['col' => 'Z',  't4' => null, 't5' => 'OCCUPATION / INCOME SOURCE', 't6' => "MAIN CATEGORIES\n(".implode(', ', self::occupationCategories()).')', 'field' => 'occupation_category', 'width' => 18],
            ['col' => 'AA', 't4' => null, 't5' => null, 't6' => 'ACTUAL OCCUPATION', 'field' => 'actual_occupation', 'width' => 18],
            ['col' => 'AB', 't4' => null, 't5' => null, 't6' => 'STATUS', 'field' => 'occupation_status', 'width' => 14],
            ['col' => 'AC', 't4' => null, 't5' => null, 't6' => 'INDUSTRY', 'field' => 'industry', 'width' => 18],
            ['col' => 'AD', 't4' => null, 't5' => 'NUMBER OF DEPENDENTS', 't6' => null, 'field' => 'number_of_dependents', 'width' => 12],
            ['col' => 'AE', 't4' => null, 't5' => "RELIGION / SOCIAL AFFILIATION\n(R. Catholic, Islam, Christian, Others, No Religion, Prefer not to say)", 't6' => null, 'field' => 'religion', 'width' => 20],
            ['col' => 'AF', 't4' => null, 't5' => 'ANNUAL INCOME', 't6' => null, 'field' => 'annual_income', 'width' => 14, 'money' => true],

            ['col' => 'AG', 't4' => 'TERMINATION OF MEMBERSHIP', 't5' => 'BOD RESOLUTION NUMBER', 't6' => null, 'field' => 'termination_bod_res', 'width' => 16],
            ['col' => 'AH', 't4' => null, 't5' => 'DATE', 't6' => null, 'field' => 'termination_date', 'width' => 12, 'date' => true],

            ['col' => 'AI', 't4' => 'ETHNICITY / ETHNIC GROUP', 't5' => null, 't6' => null, 'field' => 'ethnicity', 'width' => 14],
            ['col' => 'AJ', 't4' => 'PWD', 't5' => '(YES / NO)', 't6' => null, 'field' => null, 'width' => 10,
                'value' => fn ($m) => is_null($m->is_pwd) ? null : ($m->is_pwd ? 'YES' : 'NO')],
            ['col' => 'AK', 't4' => null, 't5' => 'Specify Disability', 't6' => null, 'field' => 'disability', 'width' => 16],

            ['col' => 'AL', 't4' => 'CONTACT NUMBER', 't5' => null, 't6' => null, 'field' => 'contact_number', 'width' => 15],
            ['col' => 'AM', 't4' => 'EMAIL ADDRESS', 't5' => null, 't6' => null, 'field' => 'email_address', 'width' => 24],
            ['col' => 'AN', 't4' => 'AS OF DATE SHARE CAPITAL BALANCE', 't5' => null, 't6' => null, 'field' => 'share_capital_balance', 'width' => 16, 'money' => true],
            ['col' => 'AO', 't4' => 'AS OF DATE REGULAR SAVINGS BALANCE', 't5' => null, 't6' => null, 'field' => 'savings_balance', 'width' => 16, 'money' => true],

            ['col' => 'AP', 't4' => 'LOAN PORTFOLIO SUMMARY  (from CIC contract export)', 't5' => "No. of Loan\nContracts", 't6' => null, 'field' => 'loan_count', 'width' => 11],
            ['col' => 'AQ', 't4' => null, 't5' => "Total Financed\nAmount", 't6' => null, 'field' => 'total_financed', 'width' => 16, 'money' => true],
            ['col' => 'AR', 't4' => null, 't5' => "Total Outstanding\nBalance", 't6' => null, 'field' => 'total_outstanding', 'width' => 16, 'money' => true],
            ['col' => 'AS', 't4' => null, 't5' => "Total Past-Due\nAmount", 't6' => null, 'field' => 'total_overdue_amount', 'width' => 16, 'money' => true],
        ];
    }

    /**
     * Merge ranges for the header block (rows 1-6). Mirrors the CDA template.
     */
    public static function mergeRanges(): array
    {
        return [
            'A1:P1', 'A2:P2', 'A3:AO3',
            'A4:D4', 'E4:E6', 'F4:F6', 'G4:P4', 'Q4:AF4', 'AG4:AH4', 'AI4:AI6',
            'AJ4:AK4', 'AL4:AL6', 'AM4:AM6', 'AN4:AN6', 'AO4:AO6', 'AP4:AS4',
            'A5:A6', 'B5:B6', 'C5:C6', 'D5:D6', 'G5:G6', 'H5:I5', 'J5:M5', 'N5:P5',
            'Q5:S5', 'T5:T6', 'U5:U6', 'V5:V6', 'W5:W6', 'X5:X6', 'Y5:Y6', 'Z5:AC5',
            'AD5:AD6', 'AE5:AE6', 'AF5:AF6', 'AG5:AG6', 'AH5:AH6', 'AJ5:AJ6', 'AK5:AK6',
            'AP5:AP6', 'AQ5:AQ6', 'AR5:AR6', 'AS5:AS6',
        ];
    }

    public const LAST_COLUMN = 'AS';

    public const FIRST_DATA_ROW = 7;
}
