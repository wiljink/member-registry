<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\MemberLoan;
use App\Models\User;
use App\Services\RegistryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RegistryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function importFixture(string $name, string $type, ?string $branchOverride = null, string $period = '2026-09'): void
    {
        $path = base_path("tests/fixtures/{$name}");
        app(RegistryImporter::class)->run(
            new UploadedFile($path, $name, 'text/csv', null, true),
            $type,
            $period,
            $branchOverride,
        );
    }

    public function test_members_import_decodes_codes_and_parses_date_accepted(): void
    {
        $this->importFixture('members.csv', 'members');

        $this->assertSame(3, Member::count());

        $bernard = Member::where('cid', '1237')->firstOrFail();
        $this->assertSame('CELEDIO', $bernard->last_name);
        $this->assertSame('Tagbilaran Branch', $bernard->branch);   // from the row's Branch Code
        $this->assertSame('Male', $bernard->sex_assigned_at_birth);
        $this->assertSame('Single', $bernard->civil_status);
        $this->assertSame('2011-01-21', $bernard->date_accepted->toDateString());
        $this->assertNull($bernard->tin);   // no NID in the source row
        // present_address / sex / civil_status / date_accepted are auto-seeded → partially done
        $this->assertSame('in_progress', $bernard->completion_status);

        // occupation main category seeded from the per-CID CIC code ("004" = Pensioner)
        $this->assertSame('Retired', $bernard->occupation_category);

        $jayson = Member::where('cid', '1256')->firstOrFail();
        $this->assertSame('Loon Branch', $jayson->branch);          // different branch, same file
        $this->assertSame('Married', $jayson->civil_status);
        $this->assertSame('09171234567', $jayson->contact_number);
        $this->assertSame('123-456-789-000', $jayson->tin);   // seeded from the CIC NID field
        $this->assertSame('Government employee', $jayson->occupation_category);   // decoded from the text label

        $maria = Member::where('cid', '9999')->firstOrFail();
        $this->assertNull($maria->date_accepted);
        $this->assertSame('maria@example.com', $maria->email_address);
        $this->assertNull($maria->occupation_category);   // CIC "Other" → left for staff to fill

        // every row is tagged with the selected data month (stored first-of-month)
        $this->assertSame('2026-09-01', $bernard->data_period->toDateString());
    }

    public function test_period_filter_and_history(): void
    {
        $this->importFixture('members.csv', 'members', null, '2026-08');
        $this->importFixture('members.csv', 'members', null, '2026-09');   // re-import → newer month wins

        $this->assertEquals(['2026-09'], Member::dataPeriods()->all());
        $this->assertSame(3, Member::query()
            ->whereYear('data_period', 2026)->whereMonth('data_period', 9)->count());

        $batch = ImportBatch::orderByDesc('id')->first();
        $this->assertSame('2026-09-01', $batch->period->toDateString());
    }

    public function test_one_file_populates_multiple_branches(): void
    {
        $this->importFixture('members.csv', 'members');

        $this->assertSame(2, Member::where('branch', 'Tagbilaran Branch')->count());
        $this->assertSame(1, Member::where('branch', 'Loon Branch')->count());

        // the branch list is derived from the data itself
        $this->assertEqualsCanonicalizing(
            ['Loon Branch', 'Tagbilaran Branch'],
            Member::branchNames()->all(),
        );
    }

    public function test_branch_override_wins_over_the_file(): void
    {
        $this->importFixture('members.csv', 'members', 'Ubay Branch');

        $this->assertSame(3, Member::where('branch', 'Ubay Branch')->count());
    }

    public function test_classifier_derives_membership_fields(): void
    {
        $this->importFixture('members.csv', 'members');
        $this->importFixture('loans.csv', 'loans');

        // 1237: share 2000 + savings 500 = 2500 < 3000; delinquent loan; overdue in 12mo
        $bernard = Member::where('cid', '1237')->firstOrFail();
        $this->assertSame('Associate', $bernard->membership_type);
        $this->assertSame('Non Full-fledged', $bernard->membership_kind);
        $this->assertSame('Non-MIGS', $bernard->migs_status);
        $this->assertSame('Inactive', $bernard->activity_status);

        // 1256: 5000 + 100 = 5100 >= 3000; loan current; has activity
        $jayson = Member::where('cid', '1256')->firstOrFail();
        $this->assertSame('Regular', $jayson->membership_type);
        $this->assertSame('Full-fledged', $jayson->membership_kind);
        $this->assertSame('MIGS', $jayson->migs_status);
        $this->assertSame('Active', $jayson->activity_status);

        // 9999: no balances -> type/kind/active can't be computed; no loan -> MIGS
        $maria = Member::where('cid', '9999')->firstOrFail();
        $this->assertNull($maria->membership_type);
        $this->assertNull($maria->membership_kind);
        $this->assertNull($maria->activity_status);
        $this->assertSame('MIGS', $maria->migs_status);
    }

    public function test_classifier_respects_a_manual_override_across_reimports(): void
    {
        $this->importFixture('members.csv', 'members');
        $this->actingAs(User::factory()->create());
        $jayson = Member::where('cid', '1256')->firstOrFail();
        $this->assertSame('Regular', $jayson->membership_type);   // auto

        // staff overrides Type; leaves the rest
        $this->put(route('members.update', $jayson), [
            'membership_type' => 'Associate',
            'membership_kind' => $jayson->membership_kind,
            'migs_status' => $jayson->migs_status,
            'activity_status' => $jayson->activity_status,
        ])->assertRedirect();

        $jayson->refresh();
        $this->assertSame('Associate', $jayson->membership_type);
        $this->assertEqualsCanonicalizing(['membership_type'], (array) $jayson->classification_locked);

        // re-import must NOT overwrite the locked field, but may refresh the others
        $this->importFixture('members.csv', 'members');
        $jayson->refresh();
        $this->assertSame('Associate', $jayson->membership_type);        // kept
        $this->assertSame('Full-fledged', $jayson->membership_kind);     // still auto
    }

    public function test_reimport_keeps_manual_values(): void
    {
        $this->importFixture('members.csv', 'members');

        $bernard = Member::where('cid', '1237')->firstOrFail();
        $bernard->update(['tin' => '123-456-789', 'religion' => 'Roman Catholic']);
        $firstImportedAt = $bernard->fresh()->last_imported_at;

        sleep(1);
        $this->importFixture('members.csv', 'members');

        $bernard->refresh();
        $this->assertSame('123-456-789', $bernard->tin);
        $this->assertSame('Roman Catholic', $bernard->religion);
        $this->assertTrue($bernard->last_imported_at->gt($firstImportedAt));
    }

    public function test_loans_import_builds_rollup_and_keeps_branch(): void
    {
        $this->importFixture('members.csv', 'members');
        $this->importFixture('loans.csv', 'loans');

        $this->assertSame(3, MemberLoan::count());
        $this->assertSame('Tagbilaran Branch', MemberLoan::where('contract_no', '47000072')->value('branch'));
        $this->assertSame('Loon Branch', MemberLoan::where('contract_no', '48000205')->value('branch'));

        $bernard = Member::where('cid', '1237')->firstOrFail();
        $this->assertSame(2, $bernard->loan_count);
        $this->assertEqualsWithDelta(2478531, (float) $bernard->total_outstanding, 0.01);
        $this->assertSame(1, $bernard->delinquent_loan_count);
        $this->assertSame(45, $bernard->max_overdue_days);
    }

    public function test_update_validates_and_flips_completion(): void
    {
        $this->importFixture('members.csv', 'members');
        $member = Member::where('cid', '1237')->firstOrFail();
        $this->actingAs(User::factory()->create());

        $this->put(route('members.update', $member), ['membership_type' => 'Nonsense'])
            ->assertSessionHasErrors('membership_type');

        $payload = [
            'tin' => '111', 'date_accepted' => '2011-01-21', 'membership_type' => 'Regular',
            'membership_kind' => 'Full-fledged', 'activity_status' => 'Active',
            'present_address' => 'Dauis, Bohol', 'sex_assigned_at_birth' => 'Male',
            'civil_status' => 'Single', 'education_attainment' => 'College Graduate',
            'occupation_category' => 'Private employee', 'number_of_dependents' => 2,
            'religion' => 'Roman Catholic', 'is_pwd' => '0',
        ];
        $this->put(route('members.update', $member), $payload)->assertRedirect();

        $this->assertSame('complete', $member->fresh()->completion_status);
    }

    public function test_backfill_occupation_command_fills_only_blank_rows(): void
    {
        $seeded = Member::create([
            'cid' => 'OCC1', 'occupation_category_code' => '003', 'occupation_category' => null,
        ]);
        $staffSet = Member::create([
            'cid' => 'OCC2', 'occupation_category_code' => '002', 'occupation_category' => 'Employer',
        ]);
        $other = Member::create([
            'cid' => 'OCC3', 'occupation_category_code' => '007', 'occupation_category' => null,
        ]);
        $zeroStripped = Member::create([
            'cid' => 'OCC4', 'occupation_category_code' => '2', 'occupation_category' => null,
        ]);

        $this->artisan('app:backfill-occupation')->assertSuccessful();

        $this->assertSame('Self-employed', $seeded->fresh()->occupation_category);        // filled from "003"
        $this->assertSame('Employer', $staffSet->fresh()->occupation_category);           // staff value untouched
        $this->assertNull($other->fresh()->occupation_category);                          // "Other" stays blank
        $this->assertSame('Government employee', $zeroStripped->fresh()->occupation_category); // Excel-stripped "2"
    }

    public function test_import_history_entry_can_be_deleted_without_touching_data(): void
    {
        $this->importFixture('members.csv', 'members');
        $batch = ImportBatch::latest('id')->firstOrFail();
        $this->actingAs(User::factory()->create());

        $this->delete(route('imports.destroy', $batch))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('import_batches', ['id' => $batch->id]);
        $this->assertSame(3, Member::count());   // imported rows untouched
    }

    public function test_deleting_an_import_entry_requires_auth(): void
    {
        $this->importFixture('members.csv', 'members');
        $batch = ImportBatch::latest('id')->firstOrFail();

        $this->delete(route('imports.destroy', $batch))->assertRedirect(route('login'));
        $this->assertDatabaseHas('import_batches', ['id' => $batch->id]);
    }

    public function test_import_history_renders_a_delete_control_per_row(): void
    {
        $this->importFixture('members.csv', 'members');
        $batch = ImportBatch::latest('id')->firstOrFail();
        $this->actingAs(User::factory()->create());

        $this->get(route('imports.index'))
            ->assertOk()
            ->assertSee(route('imports.destroy', $batch))
            ->assertSee('Delete');
    }

    public function test_registry_export_downloads_whole_coop_and_per_branch(): void
    {
        $this->importFixture('members.csv', 'members');
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('exports.registry'));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', $response->headers->get('content-type'));

        $this->get(route('exports.registry', ['branch' => 'Loon Branch']))->assertOk();
        $this->get(route('exports.registry', ['period' => '2026-09']))->assertOk();
    }

    public function test_import_requires_a_file_and_a_month(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('imports.index'))
            ->post(route('imports.members'), [])
            ->assertSessionHasErrors(['file', 'period']);

        $file = UploadedFile::fake()->createWithContent(
            'members.csv', file_get_contents(base_path('tests/fixtures/members.csv'))
        );
        $this->post(route('imports.members'), ['file' => $file, 'period' => '2026-09'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Tagbilaran Branch', Member::where('cid', '1237')->value('branch'));
        $this->assertSame('Loon Branch', Member::where('cid', '1256')->value('branch'));
        $this->assertSame('2026-09-01', Member::where('cid', '1237')->first()->data_period->toDateString());
    }
}
