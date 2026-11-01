<?php

namespace Tests\Feature\Api;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Evaluation\Models\EvaluationProfile;
use App\Domain\Evaluation\Models\EvaluationProfileVersion;
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

class MonthlyReportApiTest extends TestCase
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
    private function scenario(string $startDay = 'saturday'): array
    {
        $center = Center::factory()->create([
            'weekly_evaluation_start_day' => $startDay,
            'timezone' => 'Asia/Riyadh',
        ]);
        $manager = User::factory()->manager()->for($center)->create();
        $teacher = User::factory()->teacher()->for($center)->create();
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create(['enrolled_at' => '2026-01-01']);

        app(AddStudentToGroup::class)->execute($group, $manager, $student->id);

        return compact('center', 'manager', 'teacher', 'group', 'student');
    }

    private function memorization(array $s, string $date, string $pages, ?Student $student = null): Memorization
    {
        return Memorization::factory()->for($s['center'])->create([
            'student_id' => ($student ?? $s['student'])->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => $date,
            'amount_pages' => $pages,
        ]);
    }

    private function review(array $s, string $date, string $pages): Review
    {
        return Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $date,
            'amount_pages' => $pages,
        ]);
    }

    private function recitation(array $s, string $date, string $grade, int $hifz = 0): Recitation
    {
        return Recitation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => $date,
            'grade' => $grade,
            'hifz_errors' => $hifz,
            'tajweed_errors' => 0,
            'reading_errors' => 0,
        ]);
    }

    private function attendance(array $s, string $date, string $status): Attendance
    {
        return Attendance::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'attendance_date' => $date,
            'status' => $status,
            'late_minutes' => null,
        ]);
    }

    private function evaluationFor(
        array $s,
        Student $student,
        string $weekStart = '2026-10-03',
        string $required = '1.00',
        string $status = 'computed',
        bool $complete = true,
        ?Group $group = null,
    ): WeeklyEvaluation {
        $start = CarbonImmutable::parse($weekStart);

        return WeeklyEvaluation::factory()->for($s['center'])->create([
            'student_id' => $student->id,
            'group_id' => ($group ?? $s['group'])->id,
            'teacher_id' => $s['teacher']->id,
            'week_key' => $start->toDateString(),
            'week_start_date' => $start->toDateString(),
            'week_end_date' => $start->addDays(6)->toDateString(),
            'required_pages' => $required,
            'status' => $status,
            'is_complete' => $complete,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function ruleSet(array $criteria): array
    {
        return ['version' => 'v1', 'criteria' => $criteria, 'recitation_grade_scores' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function criterion(string $key, string $weight): array
    {
        return [
            'key' => $key,
            'weight' => $weight,
            'calculator' => $key,
            'missing_data_policy' => 'block',
            'params' => ['required_pages' => '1'],
        ];
    }

    private function profileVersion(
        array $s,
        array $ruleSet,
        string $requiredPages = '1.00',
        int $version = 1,
        string $effectiveFrom = '2026-01-01',
    ): EvaluationProfileVersion {
        $profile = EvaluationProfile::firstOrCreate([
            'center_id' => $s['center']->id,
            'student_id' => $s['student']->id,
        ]);

        return EvaluationProfileVersion::factory()->for($s['center'])->create([
            'profile_id' => $profile->id,
            'version_number' => $version,
            'required_pages' => $requiredPages,
            'effective_from' => $effectiveFrom,
            'rule_set' => $ruleSet,
        ]);
    }

    public function test_a_guest_cannot_read_the_monthly_report(): void
    {
        $this->getJson('/api/v1/reports/monthly?month=2026-10')->assertUnauthorized();
    }

    public function test_the_report_returns_the_calendar_month(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.month.month_key', '2026-10')
            ->assertJsonPath('data.month.start_date', '2026-10-01')
            ->assertJsonPath('data.month.end_date', '2026-10-31')
            ->assertJsonPath('data.totals.students', 1)
            ->assertJsonPath('data.students.0.student.id', $s['student']->id);
    }

    public function test_the_month_is_not_derived_from_the_week_start_day(): void
    {
        $s = $this->scenario('monday');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-02')
            ->assertOk()
            ->assertJsonPath('data.month.start_date', '2026-02-01')
            ->assertJsonPath('data.month.end_date', '2026-02-28');
    }

    public function test_an_invalid_or_missing_month_is_rejected(): void
    {
        $s = $this->scenario();

        foreach (['', '2026-1', '2026-13', '2026-00', 'invalid', '2026-10-01'] as $month) {
            $this->withHeaders($this->auth($s['manager']))
                ->getJson('/api/v1/reports/monthly?month='.urlencode($month))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['month']);
        }

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_records_on_the_month_boundaries_are_included_and_neighbours_are_not(): void
    {
        $s = $this->scenario();

        $this->memorization($s, '2026-09-30', '1.0000'); // previous month
        $this->memorization($s, '2026-10-01', '1.0000'); // first day
        $this->memorization($s, '2026-10-31', '0.5000'); // last day
        $this->memorization($s, '2026-11-01', '1.0000'); // next month

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.actual_pages', '1.5000')
            ->assertJsonPath('data.students.0.memorization.sessions', 2);
    }

    public function test_attendance_memorization_review_and_recitation_are_aggregated(): void
    {
        $s = $this->scenario();

        $this->attendance($s, '2026-10-01', 'present');
        $this->attendance($s, '2026-10-02', 'present');
        $this->attendance($s, '2026-10-03', 'late');
        $this->attendance($s, '2026-10-04', 'excused_absence');
        $this->attendance($s, '2026-10-05', 'unexcused_absence');

        $this->memorization($s, '2026-10-01', '0.5000');
        $this->memorization($s, '2026-10-08', '1.0000');

        $this->review($s, '2026-10-09', '0.7500');
        $this->review($s, '2026-10-10', '0.2500');

        $this->recitation($s, '2026-10-11', 'good', 2);
        $this->recitation($s, '2026-10-12', 'good', 1);
        $this->recitation($s, '2026-10-13', 'excellent');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.attendance.present', 2)
            ->assertJsonPath('data.students.0.attendance.late', 1)
            ->assertJsonPath('data.students.0.attendance.excused_absence', 1)
            ->assertJsonPath('data.students.0.attendance.unexcused_absence', 1)
            ->assertJsonPath('data.students.0.memorization.actual_pages', '1.5000')
            ->assertJsonPath('data.students.0.memorization.sessions', 2)
            ->assertJsonPath('data.students.0.review.pages', '1.0000')
            ->assertJsonPath('data.students.0.review.sessions', 2)
            ->assertJsonPath('data.students.0.recitation.sessions', 3)
            ->assertJsonPath('data.students.0.recitation.grades.good', 2)
            ->assertJsonPath('data.students.0.recitation.grades.excellent', 1)
            ->assertJsonPath('data.students.0.recitation.errors.hifz', 3);
    }

    public function test_weekly_evaluations_are_counted_as_stored(): void
    {
        $s = $this->scenario();

        $this->evaluationFor($s, $s['student'], '2026-10-03', '1.00', 'approved');
        $this->evaluationFor($s, $s['student'], '2026-10-10', '1.00', 'approved');
        $this->evaluationFor($s, $s['student'], '2026-10-17', '1.00', 'approved');
        $this->evaluationFor($s, $s['student'], '2026-10-24', '1.00', 'computed');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.weekly_evaluations.count', 4)
            ->assertJsonPath('data.students.0.weekly_evaluations.completed', 4)
            ->assertJsonPath('data.students.0.weekly_evaluations.approved', 3)
            ->assertJsonPath('data.students.0.weekly_evaluations.computed', 1);
    }

    public function test_the_monthly_requirement_is_the_sum_of_the_stored_evaluations(): void
    {
        $s = $this->scenario();

        $this->evaluationFor($s, $s['student'], '2026-10-03', '1.00');
        $this->evaluationFor($s, $s['student'], '2026-10-10', '1.00');
        $this->evaluationFor($s, $s['student'], '2026-10-17', '2.00');
        $this->evaluationFor($s, $s['student'], '2026-10-24', '0.50');

        $this->memorization($s, '2026-10-05', '4.5000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.required_pages', '4.50')
            ->assertJsonPath('data.students.0.memorization.actual_pages', '4.5000')
            ->assertJsonPath('data.students.0.memorization.achievement_percentage', '100.00');
    }

    public function test_achievement_is_uncapped(): void
    {
        $s = $this->scenario();

        $this->evaluationFor($s, $s['student'], '2026-10-03', '2.00');
        $this->memorization($s, '2026-10-05', '6.0000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.required_pages', '2.00')
            ->assertJsonPath('data.students.0.memorization.achievement_percentage', '300.00');
    }

    public function test_a_missing_evaluation_leaves_the_requirement_and_ratio_undefined(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-05', '2.0000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.required_pages', '0.00')
            ->assertJsonPath('data.students.0.memorization.actual_pages', '2.0000')
            ->assertJsonPath('data.students.0.memorization.achievement_percentage', null)
            ->assertJsonPath('data.students.0.weekly_evaluations.count', 0)
            ->assertJsonPath('data.students.0.weekly_evaluations.approved', 0);
    }

    public function test_a_partial_month_does_not_invent_the_missing_weeks(): void
    {
        $s = $this->scenario();

        // Two of the month's four weeks were evaluated; the other two are not
        // replaced by a default or by the current profile.
        $this->evaluationFor($s, $s['student'], '2026-10-03', '1.00');
        $this->evaluationFor($s, $s['student'], '2026-10-10', '1.00');
        $this->profileVersion($s, $this->ruleSet([$this->criterion('memorization_pages', '100')]), '9.00');
        $this->memorization($s, '2026-10-05', '3.0000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.required_pages', '2.00')
            ->assertJsonPath('data.students.0.memorization.achievement_percentage', '150.00')
            ->assertJsonPath('data.students.0.weekly_evaluations.count', 2);
    }

    public function test_changing_the_profile_does_not_reinterpret_stored_evaluations(): void
    {
        $s = $this->scenario();
        $this->profileVersion($s, $this->ruleSet([$this->criterion('memorization_pages', '100')]), '1.00');
        $this->memorization($s, '2026-10-05', '1.0000');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-05',
        ])->assertCreated();

        $this->profileVersion($s, $this->ruleSet([$this->criterion('memorization_pages', '100')]), '9.00', 2, '2026-10-01');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.required_pages', '1.00')
            ->assertJsonPath('data.students.0.weekly_evaluations.count', 1);
    }

    public function test_the_report_never_creates_a_weekly_evaluation(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-05', '1.0000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk();

        $this->assertSame(0, WeeklyEvaluation::query()->count());
    }

    public function test_a_historical_evaluation_keeps_its_own_group_and_teacher(): void
    {
        $s = $this->scenario();
        $this->evaluationFor($s, $s['student'], '2026-10-03', '1.00');

        $newTeacher = User::factory()->teacher()->for($s['center'])->create();
        $newGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $newTeacher->id]);
        app(AddStudentToGroup::class)->execute($newGroup, $s['manager'], $s['student']->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.group.id', $s['group']->id)
            ->assertJsonPath('data.students.0.teacher.id', $s['teacher']->id);
    }

    public function test_a_manager_sees_the_center_and_a_teacher_only_their_groups(): void
    {
        $s = $this->scenario();

        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $otherStudent = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($otherGroup, $s['manager'], $otherStudent->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.totals.students', 2);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.totals.students', 1)
            ->assertJsonPath('data.students.0.student.id', $s['student']->id);
    }

    public function test_a_teacher_cannot_read_another_teachers_group(): void
    {
        $s = $this->scenario();

        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $otherStudent = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($otherGroup, $s['manager'], $otherStudent->id);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reports/monthly?month=2026-10&group_id='.$otherGroup->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 0);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reports/monthly?month=2026-10&student_id='.$otherStudent->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 0);
    }

    public function test_another_center_never_appears(): void
    {
        $s = $this->scenario();

        $otherCenter = Center::factory()->create(['timezone' => 'Asia/Riyadh']);
        $otherManager = User::factory()->manager()->for($otherCenter)->create();
        $otherTeacher = User::factory()->teacher()->for($otherCenter)->create();
        $otherGroup = Group::factory()->for($otherCenter)->create(['teacher_id' => $otherTeacher->id]);
        $foreignStudent = Student::factory()->for($otherCenter)->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($otherGroup, $otherTeacher, $foreignStudent->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.totals.students', 1);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10&group_id='.$otherGroup->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 0);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10&student_id='.$foreignStudent->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 0);
    }

    public function test_the_group_and_student_filters_narrow_the_rows(): void
    {
        $s = $this->scenario();

        $second = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($s['group'], $s['manager'], $second->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10&student_id='.$second->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 1)
            ->assertJsonPath('data.students.0.student.id', $second->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10&group_id='.$s['group']->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 2);

        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $s['teacher']->id]);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10&group_id='.$otherGroup->id.'&student_id='.$s['student']->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 0);
    }

    public function test_soft_deleted_records_and_evaluations_are_excluded(): void
    {
        $s = $this->scenario();

        $this->attendance($s, '2026-10-01', 'present')->delete();
        $this->memorization($s, '2026-10-02', '1.0000')->delete();
        $this->review($s, '2026-10-03', '1.0000')->delete();
        $this->recitation($s, '2026-10-04', 'good', 4)->delete();
        $this->evaluationFor($s, $s['student'], '2026-10-03', '1.00')->delete();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.attendance.present', 0)
            ->assertJsonPath('data.students.0.memorization.actual_pages', '0.0000')
            ->assertJsonPath('data.students.0.memorization.required_pages', '0.00')
            ->assertJsonPath('data.students.0.review.pages', '0.0000')
            ->assertJsonPath('data.students.0.recitation.sessions', 0)
            ->assertJsonPath('data.students.0.weekly_evaluations.count', 0);
    }

    public function test_a_student_with_no_activity_still_returns_a_row(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.totals.students', 1)
            ->assertJsonPath('data.students.0.attendance', [
                'present' => 0,
                'late' => 0,
                'excused_absence' => 0,
                'unexcused_absence' => 0,
            ])
            ->assertJsonPath('data.students.0.memorization.actual_pages', '0.0000')
            ->assertJsonPath('data.students.0.memorization.required_pages', '0.00')
            ->assertJsonPath('data.students.0.memorization.achievement_percentage', null)
            ->assertJsonPath('data.students.0.review.pages', '0.0000')
            ->assertJsonPath('data.students.0.recitation.sessions', 0)
            ->assertJsonPath('data.students.0.weekly_evaluations.count', 0);
    }

    public function test_page_amounts_keep_decimal_precision(): void
    {
        $s = $this->scenario();

        foreach (['0.1000', '0.2000', '0.2500', '0.2500', '0.5000', '0.5000', '1.2500', '1.7500'] as $amount) {
            $this->memorization($s, '2026-10-05', $amount);
        }

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.actual_pages', '4.8000')
            ->assertJsonPath('data.students.0.memorization.sessions', 8);
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_students(): void
    {
        $s = $this->scenario();
        $this->seedMonth($s, $s['student']);

        DB::enableQueryLog();
        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk();
        $baseline = count(DB::getQueryLog());

        for ($i = 0; $i < 8; $i++) {
            $student = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
            app(AddStudentToGroup::class)->execute($s['group'], $s['manager'], $student->id);
            $this->seedMonth($s, $student);
        }

        DB::flushQueryLog();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.totals.students', 9)
            ->assertJsonPath('data.students.0.weekly_evaluations.count', 2);

        $after = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($baseline, $after, 'The report must not issue one query per student.');
    }

    /**
     * Two evaluations plus several records of every other type, so the N+1
     * guard covers every source and not just the empty case.
     */
    private function seedMonth(array $s, Student $student): void
    {
        $this->evaluationFor($s, $student, '2026-10-03', '1.00');
        $this->evaluationFor($s, $student, '2026-10-10', '1.00');

        foreach (['2026-10-01', '2026-10-02'] as $date) {
            Attendance::factory()->for($s['center'])->create([
                'student_id' => $student->id,
                'group_id' => $s['group']->id,
                'teacher_id' => $s['teacher']->id,
                'attendance_date' => $date,
                'status' => 'present',
                'late_minutes' => null,
            ]);
        }

        foreach (['0.5000', '1.0000'] as $index => $amount) {
            $this->memorization($s, '2026-10-0'.($index + 4), $amount, $student);
        }

        Review::factory()->for($s['center'])->create([
            'student_id' => $student->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => '2026-10-07',
            'amount_pages' => '0.5000',
        ]);

        Recitation::factory()->for($s['center'])->create([
            'student_id' => $student->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => '2026-10-11',
            'grade' => 'good',
            'hifz_errors' => 1,
            'tajweed_errors' => 0,
            'reading_errors' => 0,
        ]);
    }

    public function test_the_month_does_not_depend_on_the_current_day(): void
    {
        $s = $this->scenario();

        // On the 31st a naive `createFromFormat('Y-m')` fills the day with 31 and
        // overflows November into December.
        $this->travelTo(CarbonImmutable::parse('2026-12-31 10:00:00', 'Asia/Riyadh'));

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-11')
            ->assertOk()
            ->assertJsonPath('data.month.month_key', '2026-11')
            ->assertJsonPath('data.month.start_date', '2026-11-01')
            ->assertJsonPath('data.month.end_date', '2026-11-30');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-02')
            ->assertOk()
            ->assertJsonPath('data.month.start_date', '2026-02-01')
            ->assertJsonPath('data.month.end_date', '2026-02-28');

        $this->travelBack();
    }

    public function test_a_half_evaluated_month_uses_only_the_stored_requirements(): void
    {
        $s = $this->scenario();

        // Four weeks in the month, only two evaluated.
        $this->evaluationFor($s, $s['student'], '2026-10-03', '1.00');
        $this->evaluationFor($s, $s['student'], '2026-10-10', '1.00');

        $this->memorization($s, '2026-10-05', '2.0000');
        $this->memorization($s, '2026-10-12', '2.0000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.required_pages', '2.00')
            ->assertJsonPath('data.students.0.memorization.actual_pages', '4.0000')
            ->assertJsonPath('data.students.0.memorization.achievement_percentage', '200.00')
            ->assertJsonPath('data.students.0.weekly_evaluations.count', 2);
    }

    public function test_achievement_reference_values(): void
    {
        $s = $this->scenario();

        $cases = [
            ['required' => '1.00', 'actual' => '0.5000', 'expected' => '50.00'],
            ['required' => '1.00', 'actual' => '1.0000', 'expected' => '100.00'],
            ['required' => '1.00', 'actual' => '3.0000', 'expected' => '300.00'],
            ['required' => '2.00', 'actual' => '4.0000', 'expected' => '200.00'],
        ];

        foreach ($cases as $case) {
            $student = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
            app(AddStudentToGroup::class)->execute($s['group'], $s['manager'], $student->id);
            $this->evaluationFor($s, $student, '2026-10-03', $case['required']);
            $this->memorization($s, '2026-10-05', $case['actual'], $student);
        }

        $rows = $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->json('data.students');

        // The first row is the scenario student, who has no records.
        $achievements = array_map(
            static fn (array $row): ?string => $row['memorization']['achievement_percentage'],
            array_slice($rows, 1),
        );

        $this->assertSame(array_column($cases, 'expected'), $achievements);
    }

    public function test_a_student_with_many_records_appears_once_with_unduplicated_totals(): void
    {
        $s = $this->scenario();

        // Two evaluations, three attendance days, three memorization records,
        // two reviews and three recitations — every source must count once.
        $this->evaluationFor($s, $s['student'], '2026-10-03', '1.00');
        $this->evaluationFor($s, $s['student'], '2026-10-10', '1.00');

        foreach (['2026-10-01', '2026-10-02', '2026-10-03'] as $date) {
            $this->attendance($s, $date, 'present');
        }

        foreach (['0.5000', '1.0000', '1.5000'] as $index => $amount) {
            $this->memorization($s, '2026-10-0'.($index + 4), $amount);
        }

        $this->review($s, '2026-10-07', '0.2500');
        $this->review($s, '2026-10-08', '0.7500');

        foreach (['2026-10-11', '2026-10-12', '2026-10-13'] as $date) {
            $this->recitation($s, $date, 'good', 1);
        }

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/monthly?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.totals.students', 1)
            ->assertJsonPath('data.students.0.attendance.present', 3)
            ->assertJsonPath('data.students.0.memorization.actual_pages', '3.0000')
            ->assertJsonPath('data.students.0.memorization.sessions', 3)
            ->assertJsonPath('data.students.0.memorization.required_pages', '2.00')
            ->assertJsonPath('data.students.0.review.pages', '1.0000')
            ->assertJsonPath('data.students.0.review.sessions', 2)
            ->assertJsonPath('data.students.0.recitation.sessions', 3)
            ->assertJsonPath('data.students.0.recitation.errors.hifz', 3)
            ->assertJsonPath('data.students.0.weekly_evaluations.count', 2);
    }

    public function test_a_teacher_cannot_filter_by_a_student_of_another_center(): void
    {
        $s = $this->scenario();

        $otherCenter = Center::factory()->create(['timezone' => 'Asia/Riyadh']);
        $otherTeacher = User::factory()->teacher()->for($otherCenter)->create();
        $otherGroup = Group::factory()->for($otherCenter)->create(['teacher_id' => $otherTeacher->id]);
        $foreignStudent = Student::factory()->for($otherCenter)->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($otherGroup, $otherTeacher, $foreignStudent->id);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reports/monthly?month=2026-10&student_id='.$foreignStudent->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 0);
    }
}
