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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeeklyReportApiTest extends TestCase
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

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function ruleSet(array $criteria): array
    {
        return ['version' => 'v1', 'criteria' => $criteria, 'recitation_grade_scores' => []];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
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

    public function test_a_guest_cannot_read_the_weekly_report(): void
    {
        $this->getJson('/api/v1/reports/weekly?week_key=2026-10-26')->assertUnauthorized();
    }

    public function test_the_report_returns_the_resolved_period_and_the_students(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.week.week_key', '2026-10-24')
            ->assertJsonPath('data.week.start_date', '2026-10-24')
            ->assertJsonPath('data.week.end_date', '2026-10-30')
            ->assertJsonPath('data.week.start_day', 'saturday')
            ->assertJsonPath('data.totals.students', 1)
            ->assertJsonPath('data.students.0.student.id', $s['student']->id)
            ->assertJsonPath('data.students.0.group.id', $s['group']->id)
            ->assertJsonPath('data.students.0.teacher.id', $s['teacher']->id)
            ->assertJsonPath('data.students.0.evaluation', null);
    }

    public function test_the_week_key_is_resolved_from_the_center_start_day(): void
    {
        $s = $this->scenario('monday');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-28')
            ->assertOk()
            ->assertJsonPath('data.week.week_key', '2026-10-26')
            ->assertJsonPath('data.week.start_date', '2026-10-26')
            ->assertJsonPath('data.week.end_date', '2026-11-01')
            ->assertJsonPath('data.week.start_day', 'monday');
    }

    public function test_a_week_start_date_is_used_verbatim_for_historical_reports(): void
    {
        $s = $this->scenario('monday');

        // A boundary stored when the center still started on Saturday must stay
        // readable even though the setting has changed since.
        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_start_date=2026-10-24')
            ->assertOk()
            ->assertJsonPath('data.week.week_key', '2026-10-24')
            ->assertJsonPath('data.week.start_date', '2026-10-24')
            ->assertJsonPath('data.week.end_date', '2026-10-30');
    }

    public function test_a_missing_week_parameter_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['week_key']);
    }

    public function test_attendance_memorization_review_and_recitation_are_reported_separately(): void
    {
        $s = $this->scenario();

        $this->attendance($s, '2026-10-25', 'present');
        $this->attendance($s, '2026-10-26', 'late');
        $this->attendance($s, '2026-10-27', 'excused_absence');
        $this->attendance($s, '2026-10-28', 'unexcused_absence');

        $this->memorization($s, '2026-10-25', '0.5000');
        $this->memorization($s, '2026-10-26', '1.0000');

        $this->review($s, '2026-10-27', '0.7500');

        $this->recitation($s, '2026-10-28', 'good', 2);

        $response = $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk();

        $response
            ->assertJsonPath('data.students.0.attendance.present', 1)
            ->assertJsonPath('data.students.0.attendance.late', 1)
            ->assertJsonPath('data.students.0.attendance.excused_absence', 1)
            ->assertJsonPath('data.students.0.attendance.unexcused_absence', 1)
            ->assertJsonPath('data.students.0.memorization.actual_pages', '1.5000')
            ->assertJsonPath('data.students.0.memorization.sessions', 2)
            ->assertJsonPath('data.students.0.memorization.required_pages', '1.00')
            ->assertJsonPath('data.students.0.memorization.achievement_percentage', '150.00')
            ->assertJsonPath('data.students.0.review.pages', '0.7500')
            ->assertJsonPath('data.students.0.review.sessions', 1)
            ->assertJsonPath('data.students.0.recitation.sessions', 1)
            ->assertJsonPath('data.students.0.recitation.grades.good', 1)
            ->assertJsonPath('data.students.0.recitation.errors.hifz', 2);
    }

    public function test_achievement_is_uncapped_and_never_normalised(): void
    {
        $s = $this->scenario();

        $otherStudent = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($s['group'], $s['manager'], $otherStudent->id);

        // 1 page of 1 → 100% for the first student, 2 pages of 1 → 200% for the
        // second: the top performer is not flattened to 100%.
        $this->memorization($s, '2026-10-25', '1.0000');
        $this->memorization($s, '2026-10-25', '2.0000', $otherStudent);

        $response = $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk();

        $byStudent = collect($response->json('data.students'))
            ->keyBy(static fn (array $row): int => $row['student']['id']);

        $this->assertSame('100.00', $byStudent[$s['student']->id]['memorization']['achievement_percentage']);
        $this->assertSame('200.00', $byStudent[$otherStudent->id]['memorization']['achievement_percentage']);
    }

    public function test_a_150_percent_week_is_reported_as_is(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.5000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.achievement_percentage', '150.00');
    }

    public function test_the_required_pages_come_from_the_profile_when_no_evaluation_exists(): void
    {
        $s = $this->scenario();
        $this->profileVersion($s, $this->ruleSet([$this->criterion('memorization_pages', '100')]), '2.00');
        $this->memorization($s, '2026-10-25', '1.0000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.required_pages', '2.00')
            ->assertJsonPath('data.students.0.memorization.achievement_percentage', '50.00');
    }

    public function test_a_stored_evaluation_is_reported_from_its_snapshot(): void
    {
        $s = $this->scenario();
        $this->profileVersion($s, $this->ruleSet([$this->criterion('memorization_pages', '100')]));
        $this->memorization($s, '2026-10-25', '1.0000');

        $created = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        // Changing the profile afterwards must not rewrite the report row.
        $this->profileVersion($s, $this->ruleSet([$this->criterion('memorization_pages', '100')]), '9.00', 2, '2026-10-01');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.students.0.evaluation.id', $created->json('data.id'))
            ->assertJsonPath('data.students.0.evaluation.weighted_score', '100.00')
            ->assertJsonPath('data.students.0.evaluation.achievement_percentage', '100.00')
            ->assertJsonPath('data.students.0.evaluation.is_complete', true)
            ->assertJsonPath('data.students.0.evaluation.status', 'computed')
            ->assertJsonPath('data.students.0.memorization.required_pages', '1.00');
    }

    public function test_the_report_never_creates_a_weekly_evaluation(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.0000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.students.0.evaluation', null);

        $this->assertSame(0, WeeklyEvaluation::query()->count());
    }

    public function test_a_manager_sees_the_whole_center_and_a_teacher_only_their_groups(): void
    {
        $s = $this->scenario();

        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $otherStudent = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($otherGroup, $s['manager'], $otherStudent->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.totals.students', 2);

        $teacherRows = $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.totals.students', 1)
            ->assertJsonPath('data.students.0.student.id', $s['student']->id)
            ->json('data.students');

        $this->assertNotContains(
            $otherStudent->id,
            array_column(array_column($teacherRows, 'student'), 'id'),
        );
    }

    public function test_a_teacher_cannot_read_another_teachers_group_through_the_filter(): void
    {
        $s = $this->scenario();

        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $otherStudent = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($otherGroup, $s['manager'], $otherStudent->id);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26&group_id='.$otherGroup->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 0);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26&student_id='.$otherStudent->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 0);
    }

    public function test_another_centers_students_never_appear(): void
    {
        $s = $this->scenario();

        $otherCenter = Center::factory()->create(['timezone' => 'Asia/Riyadh']);
        $otherManager = User::factory()->manager()->for($otherCenter)->create();
        $otherTeacher = User::factory()->teacher()->for($otherCenter)->create();
        $otherGroup = Group::factory()->for($otherCenter)->create(['teacher_id' => $otherTeacher->id]);
        $foreignStudent = Student::factory()->for($otherCenter)->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($otherGroup, $otherManager, $foreignStudent->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.totals.students', 1)
            ->assertJsonPath('data.students.0.student.id', $s['student']->id);

        // A foreign group id is just an empty filter, never a leak.
        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26&group_id='.$otherGroup->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 0);
    }

    public function test_the_group_and_student_filters_narrow_the_rows(): void
    {
        $s = $this->scenario();

        $second = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($s['group'], $s['manager'], $second->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26&student_id='.$second->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 1)
            ->assertJsonPath('data.students.0.student.id', $second->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26&group_id='.$s['group']->id)
            ->assertOk()
            ->assertJsonPath('data.totals.students', 2);
    }

    public function test_records_outside_the_week_are_excluded(): void
    {
        $s = $this->scenario();

        $this->memorization($s, '2026-10-23', '5.0000'); // previous week
        $this->memorization($s, '2026-10-31', '5.0000'); // next week
        $this->memorization($s, '2026-10-24', '1.0000'); // first day of the week
        $this->memorization($s, '2026-10-30', '0.5000'); // last day of the week

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.actual_pages', '1.5000')
            ->assertJsonPath('data.students.0.memorization.sessions', 2);
    }

    public function test_soft_deleted_records_are_excluded_but_a_soft_deleted_evaluation_is_null(): void
    {
        $s = $this->scenario();

        $record = $this->memorization($s, '2026-10-25', '1.0000');
        $record->delete();

        $evaluation = WeeklyEvaluation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'week_key' => '2026-10-24',
            'week_start_date' => '2026-10-24',
            'week_end_date' => '2026-10-30',
        ]);
        $evaluation->delete();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.students.0.memorization.actual_pages', '0.0000')
            ->assertJsonPath('data.students.0.evaluation', null);
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_students(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.0000');

        DB::enableQueryLog();
        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk();
        $baseline = count(DB::getQueryLog());

        for ($i = 0; $i < 8; $i++) {
            $student = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
            app(AddStudentToGroup::class)->execute($s['group'], $s['manager'], $student->id);
            $this->memorization($s, '2026-10-25', '1.0000', $student);
        }

        DB::flushQueryLog();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?week_key=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.totals.students', 9);

        $after = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($baseline, $after, 'The report must not issue one query per student.');
    }
}
