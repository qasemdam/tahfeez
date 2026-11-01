<?php

namespace Tests\Feature\Api;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards for the two query-level fixes of the performance review.
 *
 * Both assert the *shape of the SQL*, not the HTTP status: a correct response
 * can still be produced by a query that cannot use its index.
 */
class PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function auth(User $user): array
    {
        [, $plain] = $user->issueToken('perf');

        return ['Authorization' => 'Bearer '.$plain];
    }

    /**
     * @return array{center: Center, manager: User, teacher: User, group: Group, student: Student}
     */
    private function scenario(): array
    {
        $center = Center::factory()->create(['timezone' => 'Asia/Riyadh']);
        $manager = User::factory()->manager()->for($center)->create();
        $teacher = User::factory()->teacher()->for($center)->create();
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create(['enrolled_at' => '2026-01-01']);

        app(AddStudentToGroup::class)->execute($group, $manager, $student->id);

        Attendance::factory()->for($center)->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'attendance_date' => '2026-10-05',
            'status' => 'present',
            'late_minutes' => null,
        ]);

        return compact('center', 'manager', 'teacher', 'group', 'student');
    }

    /**
     * @param  array<int, array<string, mixed>>  $log
     * @return array<int, string>
     */
    private function statements(array $log): array
    {
        return array_map(static fn (array $entry): string => (string) $entry['query'], $log);
    }

    public function test_the_attendance_sheet_filters_on_the_indexed_column(): void
    {
        $s = $this->scenario();

        DB::enableQueryLog();
        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/groups/'.$s['group']->id.'/attendance?date=2026-10-05')
            ->assertOk();
        $sql = $this->statements(DB::getQueryLog());
        DB::disableQueryLog();

        $attendanceQueries = array_values(array_filter(
            $sql,
            static fn (string $q): bool => str_contains($q, 'from "attendances"'),
        ));

        $this->assertNotEmpty($attendanceQueries);
        $this->assertStringContainsString('"attendance_date" = ?', $attendanceQueries[0]);

        // A wrapped column (`strftime(...)`, `date(...)`) cannot seek the index.
        foreach ($attendanceQueries as $query) {
            $this->assertStringNotContainsString('strftime', $query);
            $this->assertStringNotContainsString('date("attendance_date")', $query);
        }
    }

    public function test_the_attendance_list_filters_on_the_indexed_column(): void
    {
        $s = $this->scenario();

        DB::enableQueryLog();
        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/attendance?date=2026-10-05')
            ->assertOk();
        $sql = $this->statements(DB::getQueryLog());
        DB::disableQueryLog();

        $attendanceQueries = array_values(array_filter(
            $sql,
            static fn (string $q): bool => str_contains($q, 'from "attendances"'),
        ));

        $this->assertNotEmpty($attendanceQueries);
        $this->assertStringContainsString('"attendance_date" = ?', $attendanceQueries[0]);
        $this->assertStringNotContainsString('strftime', $attendanceQueries[0]);
    }

    public function test_a_repeated_request_does_not_rewrite_the_token_row(): void
    {
        $s = $this->scenario();
        $headers = $this->auth($s['manager']);

        DB::enableQueryLog();
        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertOk();
        $first = $this->statements(DB::getQueryLog());
        DB::flushQueryLog();

        // Seconds later the timestamp would differ, so a naive implementation
        // writes again; the throttled one does not.
        $this->travel(30)->seconds();

        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertOk();
        $second = $this->statements(DB::getQueryLog());
        DB::disableQueryLog();

        $writes = static fn (array $statements): array => array_values(array_filter(
            $statements,
            static fn (string $q): bool => str_contains($q, 'update "api_tokens"'),
        ));

        // The first request records usage; the second must not turn a read into
        // a write on the same row.
        $this->assertCount(1, $writes($first));
        $this->assertCount(0, $writes($second));

        $this->travelBack();
    }

    public function test_usage_is_still_recorded_after_the_window(): void
    {
        $s = $this->scenario();
        $headers = $this->auth($s['manager']);

        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertOk();

        $this->travel(6)->minutes();

        DB::enableQueryLog();
        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertOk();
        $sql = $this->statements(DB::getQueryLog());
        DB::disableQueryLog();

        $writes = array_values(array_filter(
            $sql,
            static fn (string $q): bool => str_contains($q, 'update "api_tokens"'),
        ));

        $this->assertCount(1, $writes);

        $this->travelBack();
    }
}
