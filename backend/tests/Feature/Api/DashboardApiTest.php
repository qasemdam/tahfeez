<?php

namespace Tests\Feature\Api;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function auth(User $user): array
    {
        [, $plain] = $user->issueToken('phpunit');

        return ['Authorization' => 'Bearer '.$plain];
    }

    /**
     * @return array{center: Center, manager: User, teacher: User, group: Group, student: Student}
     */
    private function scenario(): array
    {
        $center = Center::factory()->create([
            'weekly_evaluation_start_day' => 'saturday',
            'timezone' => 'Asia/Riyadh',
        ]);
        $manager = User::factory()->manager()->for($center)->create();
        $teacher = User::factory()->teacher()->for($center)->create();
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create(['enrolled_at' => '2026-01-01']);

        app(AddStudentToGroup::class)->execute($group, $manager, $student->id);

        return compact('center', 'manager', 'teacher', 'group', 'student');
    }

    private function studentIn(array $s, Group $group, string $status = 'active'): Student
    {
        $student = Student::factory()->for($s['center'])->create([
            'enrolled_at' => '2026-01-01',
            'status' => $status,
        ]);

        app(AddStudentToGroup::class)->execute($group, $s['manager'], $student->id);

        return $student;
    }

    private function memorization(array $s, Student $student, string $date, string $pages): Memorization
    {
        return Memorization::factory()->for($s['center'])->create([
            'student_id' => $student->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => $date,
            'amount_pages' => $pages,
        ]);
    }

    private function attendance(array $s, Student $student, string $date, string $status): Attendance
    {
        return Attendance::factory()->for($s['center'])->create([
            'student_id' => $student->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'attendance_date' => $date,
            'status' => $status,
            'late_minutes' => null,
        ]);
    }

    private function review(array $s, Student $student, string $date, string $pages): Review
    {
        return Review::factory()->for($s['center'])->create([
            'student_id' => $student->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $date,
            'amount_pages' => $pages,
        ]);
    }

    private function recitation(array $s, Student $student, string $date): Recitation
    {
        return Recitation::factory()->for($s['center'])->create([
            'student_id' => $student->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => $date,
            'grade' => 'good',
            'hifz_errors' => 0,
            'tajweed_errors' => 0,
            'reading_errors' => 0,
        ]);
    }

    private function evaluation(array $s, Student $student, string $weekStart, string $status = 'computed'): WeeklyEvaluation
    {
        $start = CarbonImmutable::parse($weekStart);

        return WeeklyEvaluation::factory()->for($s['center'])->create([
            'student_id' => $student->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'week_key' => $start->toDateString(),
            'week_start_date' => $start->toDateString(),
            'week_end_date' => $start->addDays(6)->toDateString(),
            'status' => $status,
        ]);
    }

    public function test_a_guest_cannot_read_the_dashboard(): void
    {
        $this->getJson('/api/v1/dashboard?month=2026-10')->assertUnauthorized();
    }

    public function test_the_dashboard_returns_the_period_and_the_centre_kpis(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.period.month', '2026-10')
            ->assertJsonPath('data.period.start_date', '2026-10-01')
            ->assertJsonPath('data.period.end_date', '2026-10-31')
            ->assertJsonPath('data.students.total', 1)
            ->assertJsonPath('data.students.active', 1)
            ->assertJsonPath('data.groups.total', 1);
    }

    public function test_the_month_is_calendar_and_never_depends_on_the_current_day(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-02')
            ->assertOk()
            ->assertJsonPath('data.period.start_date', '2026-02-01')
            ->assertJsonPath('data.period.end_date', '2026-02-28');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2028-02')
            ->assertOk()
            ->assertJsonPath('data.period.end_date', '2028-02-29'); // leap year

        $this->travelTo(CarbonImmutable::parse('2026-12-31 10:00:00', 'Asia/Riyadh'));

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-11')
            ->assertOk()
            ->assertJsonPath('data.period.month', '2026-11')
            ->assertJsonPath('data.period.end_date', '2026-11-30');

        $this->travelBack();
    }

    public function test_an_invalid_or_missing_month_is_rejected(): void
    {
        $s = $this->scenario();

        foreach (['', '2026-1', '2026-13', 'invalid', '2026-10-01'] as $month) {
            $this->withHeaders($this->auth($s['manager']))
                ->getJson('/api/v1/dashboard?month='.urlencode($month))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['month']);
        }

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_every_source_is_aggregated(): void
    {
        $s = $this->scenario();

        $this->attendance($s, $s['student'], '2026-10-01', 'present');
        $this->attendance($s, $s['student'], '2026-10-02', 'present');
        $this->attendance($s, $s['student'], '2026-10-03', 'late');
        $this->attendance($s, $s['student'], '2026-10-04', 'excused_absence');
        $this->attendance($s, $s['student'], '2026-10-05', 'unexcused_absence');

        $this->memorization($s, $s['student'], '2026-10-01', '0.5000');
        $this->memorization($s, $s['student'], '2026-10-08', '1.0000');

        $this->review($s, $s['student'], '2026-10-09', '0.7500');

        $this->recitation($s, $s['student'], '2026-10-10');
        $this->recitation($s, $s['student'], '2026-10-11');

        $this->evaluation($s, $s['student'], '2026-10-03', 'approved');
        $this->evaluation($s, $s['student'], '2026-10-10', 'computed');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.attendance.present', 2)
            ->assertJsonPath('data.attendance.late', 1)
            ->assertJsonPath('data.attendance.excused_absence', 1)
            ->assertJsonPath('data.attendance.unexcused_absence', 1)
            ->assertJsonPath('data.memorization.pages', '1.5000')
            ->assertJsonPath('data.memorization.sessions', 2)
            ->assertJsonPath('data.review.pages', '0.7500')
            ->assertJsonPath('data.review.sessions', 1)
            ->assertJsonPath('data.recitation.sessions', 2)
            ->assertJsonPath('data.weekly_evaluations.count', 2)
            ->assertJsonPath('data.weekly_evaluations.approved', 1)
            ->assertJsonPath('data.weekly_evaluations.computed', 1);
    }

    public function test_no_figure_is_multiplied_by_another_source(): void
    {
        $s = $this->scenario();

        // Two of every record: a join across sources would square the totals.
        foreach (['2026-10-01', '2026-10-02'] as $date) {
            $this->attendance($s, $s['student'], $date, 'present');
            $this->memorization($s, $s['student'], $date, '1.0000');
            $this->review($s, $s['student'], $date, '1.0000');
            $this->recitation($s, $s['student'], $date);
        }

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.attendance.present', 2)
            ->assertJsonPath('data.memorization.sessions', 2)
            ->assertJsonPath('data.memorization.pages', '2.0000')
            ->assertJsonPath('data.review.sessions', 2)
            ->assertJsonPath('data.review.pages', '2.0000')
            ->assertJsonPath('data.recitation.sessions', 2);
    }

    public function test_page_amounts_keep_decimal_precision(): void
    {
        $s = $this->scenario();

        foreach (['0.1000', '0.2000', '0.2500', '0.2500', '1.2500', '1.7500'] as $amount) {
            $this->memorization($s, $s['student'], '2026-10-05', $amount);
        }

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.memorization.pages', '3.8000')
            ->assertJsonPath('data.memorization.sessions', 6);
    }

    public function test_records_outside_the_month_are_excluded(): void
    {
        $s = $this->scenario();

        $this->memorization($s, $s['student'], '2026-09-30', '5.0000');
        $this->memorization($s, $s['student'], '2026-10-01', '1.0000');
        $this->memorization($s, $s['student'], '2026-10-31', '0.5000');
        $this->memorization($s, $s['student'], '2026-11-01', '5.0000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.memorization.pages', '1.5000')
            ->assertJsonPath('data.memorization.sessions', 2);
    }

    public function test_a_centre_without_any_activity_returns_zeros(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.total', 1)
            ->assertJsonPath('data.attendance', [
                'present' => 0,
                'late' => 0,
                'excused_absence' => 0,
                'unexcused_absence' => 0,
            ])
            ->assertJsonPath('data.memorization.pages', '0.0000')
            ->assertJsonPath('data.memorization.sessions', 0)
            ->assertJsonPath('data.review.pages', '0.0000')
            ->assertJsonPath('data.recitation.sessions', 0)
            ->assertJsonPath('data.weekly_evaluations.count', 0)
            ->assertJsonPath('data.weekly_evaluations.approved', 0)
            ->assertJsonPath('data.weekly_evaluations.computed', 0);
    }

    public function test_soft_deleted_records_are_excluded(): void
    {
        $s = $this->scenario();

        $this->attendance($s, $s['student'], '2026-10-01', 'present')->delete();
        $this->memorization($s, $s['student'], '2026-10-02', '1.0000')->delete();
        $this->review($s, $s['student'], '2026-10-03', '1.0000')->delete();
        $this->recitation($s, $s['student'], '2026-10-04')->delete();
        $this->evaluation($s, $s['student'], '2026-10-03')->delete();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.attendance.present', 0)
            ->assertJsonPath('data.memorization.pages', '0.0000')
            ->assertJsonPath('data.review.pages', '0.0000')
            ->assertJsonPath('data.recitation.sessions', 0)
            ->assertJsonPath('data.weekly_evaluations.count', 0);
    }

    public function test_a_manager_sees_the_whole_centre(): void
    {
        $s = $this->scenario();

        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $otherStudent = $this->studentIn($s, $otherGroup);

        $this->memorization($s, $s['student'], '2026-10-01', '1.0000');
        Memorization::factory()->for($s['center'])->create([
            'student_id' => $otherStudent->id,
            'group_id' => $otherGroup->id,
            'teacher_id' => $otherTeacher->id,
            'memorization_date' => '2026-10-01',
            'amount_pages' => '2.0000',
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.total', 2)
            ->assertJsonPath('data.groups.total', 2)
            ->assertJsonPath('data.memorization.pages', '3.0000');
    }

    public function test_a_teacher_only_sees_their_own_groups(): void
    {
        $s = $this->scenario();

        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $otherStudent = $this->studentIn($s, $otherGroup);

        $this->memorization($s, $s['student'], '2026-10-01', '1.0000');
        Memorization::factory()->for($s['center'])->create([
            'student_id' => $otherStudent->id,
            'group_id' => $otherGroup->id,
            'teacher_id' => $otherTeacher->id,
            'memorization_date' => '2026-10-01',
            'amount_pages' => '9.0000',
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.total', 1)
            ->assertJsonPath('data.groups.total', 1)
            ->assertJsonPath('data.memorization.pages', '1.0000');
    }

    public function test_a_teacher_cannot_widen_the_scope_with_a_group_filter(): void
    {
        $s = $this->scenario();

        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $this->studentIn($s, $otherGroup);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/dashboard?month=2026-10&group_id='.$otherGroup->id)
            ->assertOk()
            ->assertJsonPath('data.students.total', 0)
            ->assertJsonPath('data.groups.total', 0)
            ->assertJsonPath('data.memorization.pages', '0.0000');
    }

    public function test_another_centre_never_appears(): void
    {
        $s = $this->scenario();

        $otherCenter = Center::factory()->create(['timezone' => 'Asia/Riyadh']);
        $otherManager = User::factory()->manager()->for($otherCenter)->create();
        $otherTeacher = User::factory()->teacher()->for($otherCenter)->create();
        $otherGroup = Group::factory()->for($otherCenter)->create(['teacher_id' => $otherTeacher->id]);
        $foreignStudent = Student::factory()->for($otherCenter)->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($otherGroup, $otherTeacher, $foreignStudent->id);

        Memorization::factory()->for($otherCenter)->create([
            'student_id' => $foreignStudent->id,
            'group_id' => $otherGroup->id,
            'teacher_id' => $otherTeacher->id,
            'memorization_date' => '2026-10-01',
            'amount_pages' => '7.0000',
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.total', 1)
            ->assertJsonPath('data.groups.total', 1)
            ->assertJsonPath('data.memorization.pages', '0.0000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10&group_id='.$otherGroup->id)
            ->assertOk()
            ->assertJsonPath('data.groups.total', 0)
            ->assertJsonPath('data.students.total', 0);
    }

    public function test_inactive_students_count_in_the_total_but_not_in_the_active_figure(): void
    {
        $s = $this->scenario();
        $this->studentIn($s, $s['group'], 'inactive');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.total', 2)
            ->assertJsonPath('data.students.active', 1);
    }

    public function test_the_dashboard_writes_nothing(): void
    {
        $s = $this->scenario();
        $this->memorization($s, $s['student'], '2026-10-01', '1.0000');
        $this->evaluation($s, $s['student'], '2026-10-03');

        $before = [
            'weekly_evaluations' => WeeklyEvaluation::withTrashed()->count(),
            'memorization' => Memorization::withTrashed()->count(),
            'attendance' => Attendance::withTrashed()->count(),
            'review' => Review::withTrashed()->count(),
            'recitation' => Recitation::withTrashed()->count(),
        ];

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk();

        $this->assertSame($before, [
            'weekly_evaluations' => WeeklyEvaluation::withTrashed()->count(),
            'memorization' => Memorization::withTrashed()->count(),
            'attendance' => Attendance::withTrashed()->count(),
            'review' => Review::withTrashed()->count(),
            'recitation' => Recitation::withTrashed()->count(),
        ]);
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_students(): void
    {
        $s = $this->scenario();
        $this->seedRecords($s, $s['student']);

        DB::enableQueryLog();
        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/dashboard?month=2026-10')
            ->assertOk();
        $baseline = count(DB::getQueryLog());

        foreach ([8, 40] as $additional) {
            for ($i = 0; $i < $additional; $i++) {
                $this->seedRecords($s, $this->studentIn($s, $s['group']));
            }

            DB::flushQueryLog();

            $this->withHeaders($this->auth($s['manager']))
                ->getJson('/api/v1/dashboard?month=2026-10')
                ->assertOk();

            $this->assertSame(
                $baseline,
                count(DB::getQueryLog()),
                'The dashboard must not issue one query per student.',
            );
        }

        DB::disableQueryLog();
    }

    private function seedRecords(array $s, Student $student): void
    {
        $this->attendance($s, $student, '2026-10-01', 'present');
        $this->memorization($s, $student, '2026-10-01', '1.0000');
        $this->review($s, $student, '2026-10-02', '0.5000');
        $this->recitation($s, $student, '2026-10-03');
        $this->evaluation($s, $student, '2026-10-03');
    }
}
