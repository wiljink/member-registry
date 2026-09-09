<x-app-layout>
    <style>
        .mr-edit{display:grid;grid-template-columns:1fr 260px;gap:22px;align-items:start;}
        @media(max-width:1000px){.mr-edit{grid-template-columns:1fr;}}
        fieldset.mr-fs{border:1px solid var(--mr-border);border-radius:12px;padding:16px 18px 6px;margin:0 0 18px;background:#fff;}
        fieldset.mr-fs > legend{font-weight:800;font-size:.9rem;padding:0 8px;color:var(--mr-primary-dark);}
        .mr-fgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px 16px;}
        .mr-f{display:flex;flex-direction:column;gap:4px;margin-bottom:12px;}
        .mr-f-label{font-size:.75rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.03em;}
        .mr-f-hint{font-size:.72rem;color:var(--mr-muted);}
        .mr-f-err{font-size:.74rem;color:var(--mr-err);font-weight:600;}
        .mr-invalid{border-color:#fca5a5 !important;}
        .mr-aside{position:sticky;top:78px;}
        .mr-aside .mr-card{padding:16px;}
        .mr-check{font-size:.8rem;margin:5px 0;display:flex;gap:7px;align-items:center;}
        .mr-check .dot{width:9px;height:9px;border-radius:50%;flex:none;background:#cbd5e1;}
        .mr-check.on .dot{background:var(--mr-ok);}
        .mr-actions{display:flex;gap:10px;margin:6px 0 30px;}
        .mr-loans{font-size:.78rem;}
        .mr-loans td,.mr-loans th{padding:6px 8px;border-bottom:1px solid #f1f5f9;text-align:left;}
        .mr-addr-same{display:flex;align-items:center;gap:6px;font-size:.76rem;font-weight:600;
            color:var(--mr-muted);text-transform:none;letter-spacing:0;margin:-2px 0 4px;cursor:pointer;user-select:none;}
        .mr-addr-same input{width:14px;height:14px;accent-color:var(--mr-primary);cursor:pointer;}
        textarea.mr-input.mr-mirrored{background:#f1f5f9;color:#64748b;}
    </style>

    @php
        $labels = [
            'tin' => 'TIN', 'date_accepted' => 'Date accepted', 'membership_type' => 'Membership type',
            'membership_kind' => 'Membership kind', 'activity_status' => 'Active/Inactive',
            'present_address' => 'Present address', 'sex_assigned_at_birth' => 'Sex assigned at birth',
            'civil_status' => 'Civil status', 'education_attainment' => 'Education', 'occupation_category' => 'Occupation category',
            'number_of_dependents' => 'No. of dependents', 'religion' => 'Religion', 'is_pwd' => 'PWD answered',
        ];
        $missing = $member->missingFields();

        $classHint = function (string $field) use ($autoClass, $lockedClass) {
            $auto = $autoClass[$field] ?? null;
            if (is_null($auto)) {
                return 'Auto — waiting on share capital / savings data from the extract.';
            }
            return in_array($field, $lockedClass, true)
                ? "Manual override (rule says “{$auto}”). Pick “{$auto}” or clear the field to hand it back to auto."
                : "Auto-set from the rule → “{$auto}”. Change it only to override.";
        };

        $share = (float) ($member->share_capital_balance ?? 0);
        $savings = (float) ($member->savings_balance ?? 0);
        $threshold = \App\Support\MemberClassifier::threshold();
    @endphp

    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
        <div>
            <h1 class="mr-page-title">{{ $member->fullName() ?: 'Member '.$member->cid }}</h1>
            <p class="mr-page-sub">
                CID {{ $member->cid }} · {{ $member->branch ?: 'no branch' }} ·
                <span class="mr-badge {{ $member->completion_status }}">{{ str_replace('_',' ',$member->completion_status) }}</span>
            </p>
        </div>
        <a class="mr-btn ghost" href="{{ route('members.index') }}">← Back to list</a>
    </div>

    <form method="POST" action="{{ route('members.update', $member) }}">
        @csrf
        @method('PUT')

        <div class="mr-edit">
            <div>
                <fieldset class="mr-fs">
                    <legend>Identity — from CIC (read-only)</legend>
                    <div class="mr-fgrid">
                        <x-mr-field name="_ln" label="Last name" :value="$member->last_name" readonly />
                        <x-mr-field name="_fn" label="First name" :value="$member->first_name" readonly />
                        <x-mr-field name="_mn" label="Middle name" :value="$member->middle_name" readonly />
                        <x-mr-field name="_sfx" label="Suffix" :value="$member->suffix" readonly />
                        <x-mr-field name="_dob" label="Date of birth" :value="optional($member->birth_date)->format('Y-m-d')" readonly />
                        <x-mr-field name="_age" label="Age (as of {{ \App\Support\Registry::asOfDate()->format('Y-m-d') }})" :value="$member->computedAge()" readonly />
                        <x-mr-field name="_gender" label="CIC gender code" :value="$member->gender" readonly />
                        <x-mr-field name="_nid" label="CIC NID (→ TIN)" :value="$member->nid" readonly />
                        <x-mr-field name="_home" label="CIC address 1" :value="$member->home_address" readonly />
                        <x-mr-field name="_spouse" label="Spouse" :value="trim($member->spouse_first_name.' '.$member->spouse_last_name)" readonly />
                    </div>
                </fieldset>

                <fieldset class="mr-fs">
                    <legend>Membership upon acceptance</legend>
                    <div class="mr-fgrid">
                        <x-mr-field name="tin" label="TIN" :value="$member->tin" hint="Seeded from the CIC NID field — edit if the NID value is not the member's TIN." />
                        <x-mr-field name="date_accepted" label="Date accepted" type="date" :value="optional($member->date_accepted)->format('Y-m-d')" hint="Seeded from address text — verify against BOD records." />
                        <x-mr-field name="bod_res_date_confirmed" label="Date confirmed (BOD)" type="date" :value="optional($member->bod_res_date_confirmed)->format('Y-m-d')" />
                        <x-mr-field name="bod_res_number" label="BOD resolution number" :value="$member->bod_res_number" />
                        <x-mr-field name="membership_type" label="Type of membership" type="select" :options="$options['membership_type']" :value="$member->membership_type" :hint="$classHint('membership_type')" />
                        <x-mr-field name="membership_kind" label="Kind of membership" type="select" :options="$options['membership_kind']" :value="$member->membership_kind" :hint="$classHint('membership_kind')" />
                        <x-mr-field name="migs_status" label="MIGS / Non-MIGS" type="select" :options="$options['migs_status']" :value="$member->migs_status" :hint="$classHint('migs_status')" />
                        <x-mr-field name="activity_status" label="Active / Inactive" type="select" :options="$options['activity_status']" :value="$member->activity_status" :hint="$classHint('activity_status')" />
                        <x-mr-field name="initial_shares" label="Initial no. of shares" type="number" :value="$member->initial_shares" />
                        <x-mr-field name="initial_share_amount" label="Initial share amount" type="number" step="0.01" :value="$member->initial_share_amount" />
                        <x-mr-field name="initial_paid_up" label="Initial paid-up" type="number" step="0.01" :value="$member->initial_paid_up" />
                    </div>
                </fieldset>

                @php
                    $present   = old('present_address', $member->present_address);
                    $permanent = old('permanent_address', $member->permanent_address);
                    $business  = old('business_address', $member->business_address);
                    $normAddr  = fn ($v) => strtoupper(preg_replace('/\s+/', ' ', trim((string) $v)));
                    $samePerm  = filled($present) && $normAddr($present) === $normAddr($permanent);
                    $sameBiz   = filled($present) && $normAddr($present) === $normAddr($business);
                @endphp
                <fieldset class="mr-fs mr-addr">
                    <legend>Address</legend>
                    <div class="mr-fgrid">
                        <div class="mr-f">
                            <label class="mr-f-label" for="present_address">Present address</label>
                            <textarea id="present_address" name="present_address" rows="2"
                                      class="mr-input @error('present_address') mr-invalid @enderror">{{ $present }}</textarea>
                            @error('present_address')<div class="mr-f-err">{{ $message }}</div>@enderror
                        </div>

                        <div class="mr-f">
                            <label class="mr-f-label" for="permanent_address">Permanent address</label>
                            <label class="mr-addr-same"><input type="checkbox" id="same_permanent" @checked($samePerm)> Same as present</label>
                            <textarea id="permanent_address" name="permanent_address" rows="2"
                                      class="mr-input @error('permanent_address') mr-invalid @enderror">{{ $permanent }}</textarea>
                            @error('permanent_address')<div class="mr-f-err">{{ $message }}</div>@enderror
                        </div>

                        <div class="mr-f">
                            <label class="mr-f-label" for="business_address">Business address</label>
                            <label class="mr-addr-same"><input type="checkbox" id="same_business" @checked($sameBiz)> Same as present</label>
                            <textarea id="business_address" name="business_address" rows="2"
                                      class="mr-input @error('business_address') mr-invalid @enderror">{{ $business }}</textarea>
                            @error('business_address')<div class="mr-f-err">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="mr-fs">
                    <legend>Member profile</legend>
                    <div class="mr-fgrid">
                        <x-mr-field name="sex_assigned_at_birth" label="Sex assigned at birth" type="select" :options="$options['sex']" :value="$member->sex_assigned_at_birth" />
                        <x-mr-field name="gender_identity" label="Gender" type="select" :options="$options['gender_identity']" :value="$member->gender_identity" />
                        <x-mr-field name="civil_status" label="Civil status" type="select" :options="$options['civil_status']" :value="$member->civil_status" />
                        <x-mr-field name="education_attainment" label="Highest education" type="select" :options="$options['education_attainment']" :value="$member->education_attainment" />
                        <x-mr-field name="number_of_dependents" label="Number of dependents" type="number" :value="$member->number_of_dependents" />
                        <x-mr-field name="religion" label="Religion / affiliation" type="select" :options="$options['religion']" :value="$member->religion" />
                        <x-mr-field name="annual_income" label="Annual income" type="number" step="0.01" :value="$member->annual_income" />
                    </div>
                </fieldset>

                <fieldset class="mr-fs">
                    <legend>Occupation / income source</legend>
                    <div class="mr-fgrid">
                        <x-mr-field name="occupation_category" label="Main category" type="select" :options="$options['occupation_category']" :value="$member->occupation_category" />
                        <x-mr-field name="actual_occupation" label="Actual occupation" :value="$member->actual_occupation" />
                        <x-mr-field name="occupation_status" label="Status" type="select" :options="$options['occupation_status']" :value="$member->occupation_status" />
                        <x-mr-field name="industry" label="Industry" type="select" :options="$options['industry']" :value="$member->industry" />
                    </div>
                </fieldset>

                <fieldset class="mr-fs">
                    <legend>Ethnicity &amp; PWD</legend>
                    <div class="mr-fgrid">
                        <x-mr-field name="ethnicity" label="Ethnicity / ethnic group" type="select" :options="$options['ethnicity']" :value="$member->ethnicity" />
                        <x-mr-field name="is_pwd" label="PWD?" type="select" :options="['1' => 'Yes', '0' => 'No']" :value="is_null($member->is_pwd) ? null : (int) $member->is_pwd" />
                        <x-mr-field name="disability" label="Specify disability" :value="$member->disability" />
                    </div>
                </fieldset>

                <fieldset class="mr-fs">
                    <legend>Contact</legend>
                    <div class="mr-fgrid">
                        <x-mr-field name="contact_number" label="Contact number" :value="$member->contact_number" />
                        <x-mr-field name="email_address" label="Email address" type="email" :value="$member->email_address" />
                    </div>
                </fieldset>

                <fieldset class="mr-fs">
                    <legend>Financial standing — from extract (drives the classification above)</legend>
                    <div class="mr-fgrid">
                        <x-mr-field name="_scb" label="Share capital balance" :value="is_null($member->share_capital_balance) ? null : number_format($share, 2)" readonly />
                        <x-mr-field name="_svb" label="Regular savings balance" :value="is_null($member->savings_balance) ? null : number_format($savings, 2)" readonly />
                        <x-mr-field name="_comb" label="Combined vs ₱{{ number_format($threshold) }} threshold"
                            :value="($member->share_capital_balance === null && $member->savings_balance === null) ? '—' : number_format($share + $savings, 2).'  ('.($share + $savings >= $threshold ? 'meets' : 'below').')'" readonly />
                        <x-mr-field name="_asof" label="Balances as of" :value="optional($member->savings_as_of ?? $member->share_capital_as_of)->format('Y-m-d')" readonly />
                        <x-mr-field name="_deld" label="Delinquent loans" :value="$member->delinquent_loan_count" readonly />
                        <x-mr-field name="_o12" label="Overdue installments (last 12 mo)" :value="$member->overdue_installments_12mo" readonly />
                        <x-mr-field name="_lsx" label="Last savings transaction" :value="optional($member->last_savings_txn_date)->format('Y-m-d')" readonly />
                        <x-mr-field name="_lshx" label="Last share transaction" :value="optional($member->last_share_txn_date)->format('Y-m-d')" readonly />
                    </div>
                </fieldset>

                <fieldset class="mr-fs">
                    <legend>Termination of membership</legend>
                    <div class="mr-fgrid">
                        <x-mr-field name="termination_bod_res" label="BOD resolution number" :value="$member->termination_bod_res" />
                        <x-mr-field name="termination_date" label="Date" type="date" :value="optional($member->termination_date)->format('Y-m-d')" />
                    </div>
                </fieldset>

                <fieldset class="mr-fs">
                    <legend>Notes</legend>
                    <x-mr-field name="notes" label="Internal notes" type="textarea" :value="$member->notes" />
                </fieldset>

                <div class="mr-actions">
                    <button class="mr-btn" type="submit">Save member</button>
                    <a class="mr-btn ghost" href="{{ route('members.index') }}">Cancel</a>
                </div>
            </div>

            <aside class="mr-aside">
                <div class="mr-card">
                    <div style="font-weight:800;font-size:.85rem;margin-bottom:8px;">Completion checklist</div>
                    @foreach ($labels as $field => $label)
                        <div class="mr-check {{ in_array($field, $missing, true) ? '' : 'on' }}">
                            <span class="dot"></span>{{ $label }}
                        </div>
                    @endforeach
                    <div class="mr-muted" style="font-size:.72rem;margin-top:10px;">
                        {{ count($missing) === 0 ? 'All required fields filled.' : count($missing).' field(s) still needed.' }}
                    </div>
                </div>

                @if ($member->loans->isNotEmpty())
                    <div class="mr-card" style="margin-top:16px;">
                        <div style="font-weight:800;font-size:.85rem;margin-bottom:8px;">Loans ({{ $member->loans->count() }})</div>
                        <table class="mr-loans" style="width:100%;">
                            <thead><tr><th>Contract</th><th>Outstanding</th></tr></thead>
                            <tbody>
                            @foreach ($member->loans as $loan)
                                <tr><td>{{ $loan->contract_no }}</td><td>{{ number_format((float) $loan->outstanding_balance, 2) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </aside>
        </div>
    </form>

    <script>
        (function () {
            var present = document.getElementById('present_address');
            if (!present) return;

            [['same_permanent', 'permanent_address'], ['same_business', 'business_address']].forEach(function (pair) {
                var box = document.getElementById(pair[0]);
                var target = document.getElementById(pair[1]);
                if (!box || !target) return;

                function sync() {
                    if (box.checked) {
                        target.value = present.value;
                        target.readOnly = true;
                        target.classList.add('mr-mirrored');
                    } else {
                        target.readOnly = false;
                        target.classList.remove('mr-mirrored');
                    }
                }
                box.addEventListener('change', sync);
                present.addEventListener('input', function () { if (box.checked) target.value = present.value; });
                sync();
            });
        })();
    </script>
</x-app-layout>
