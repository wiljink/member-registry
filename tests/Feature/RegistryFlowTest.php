<?php

namespace Tests\Feature;

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

    protected function importFixture(string $name, string $type): void
    {
        $path = base_path("tests/fixtures/{$name}");
        app(RegistryImporter::class)->run(
            new UploadedFile($path, $name, 'text/csv', null, true),
            $type,
        );
    }

    public function test_members_import_decodes_codes_and_parses_date_accepted(): void
    {
        $this->importFixture('members.csv', 'members');

        $this->assertSame(3, Member::count());

        $bernard = Member::where('cid', '1237')->firstOrFail();
        $this->assertSame('CELEDIO', $bernard->last_name);
        $this->assertSame('Male', $bernard->sex_assigned_at_birth);
        $this->assertSame('Single', $bernard->civil_status);
        $this->assertSame('2011-01-21', $bernard->date_accepted->toDateString());
        $this->assertNull($bernard->tin);
        // present_address / sex / civil_status / date_accepted are auto-seeded → partially done
        $this->assertSame('in_progress', $bernard->completion_status);

        $jayson = Member::where('cid', '1256')->firstOrFail();
        $this->assertSame('Married', $jayson->civil_status);
        $this->assertSame('09171234567', $jayson->contact_number);

        $maria = Member::where('cid', '9999')->firstOrFail();
        $this->assertNull($maria->date_accepted);
        $this->assertSame('maria@example.com', $maria->email_address);
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

    public function test_loans_import_builds_rollup(): void
    {
        $this->importFixture('members.csv', 'members');
        $this->importFixture('loans.csv', 'loans');

        $this->assertSame(3, MemberLoan::count());

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
            'occupation_category' => 'Private', 'number_of_dependents' => 2,
            'religion' => 'Roman Catholic', 'is_pwd' => '0',
        ];
        $this->put(route('members.update', $member), $payload)->assertRedirect();

        $this->assertSame('complete', $member->fresh()->completion_status);
    }

    public function test_registry_export_downloads(): void
    {
        $this->importFixture('members.csv', 'members');
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('exports.registry'));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', $response->headers->get('content-type'));
    }
}
