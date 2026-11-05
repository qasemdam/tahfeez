<?php

namespace Tests\Support;

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
use Illuminate\Support\Facades\DB;

/**
 * The fixture used by the report export tests: one center, one manager, one
 * teacher, one group and one student, plus helpers to record activity.
 *
 * Kept separate from the report tests so an export fixture can grow (a second
 * group, a foreign center) without touching them.
 */
trait ReportExportScenario
{
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
        $student = Student::factory()->for($center)->create([
            'enrolled_at' => '2026-01-01',
            'full_name' => 'عبد الله محمد',
        ]);

        app(AddStudentToGroup::class)->execute($group, $manager, $student->id);

        return compact('center', 'manager', 'teacher', 'group', 'student');
    }

    private function addStudent(array $s, ?Group $group = null, string $name = 'سارة أحمد'): Student
    {
        $student = Student::factory()->for($s['center'])->create([
            'enrolled_at' => '2026-01-01',
            'full_name' => $name,
        ]);

        app(AddStudentToGroup::class)->execute($group ?? $s['group'], $s['manager'], $student->id);

        return $student;
    }

    /**
     * Tops the center up to `$total` students with an active membership in the
     * scenario group, using bulk inserts so a 200-row export fixture stays fast.
     */
    private function topUpStudents(array $s, int $total): void
    {
        $existing = (int) Student::query()->where('center_id', $s['center']->id)->count();
        $missing = max($total - $existing, 0);

        if ($missing === 0) {
            return;
        }

        $now = now();
        $students = [];

        for ($i = 0; $i < $missing; $i++) {
            $students[] = [
                'center_id' => $s['center']->id,
                'code' => 'BULK-'.($existing + $i),
                'full_name' => 'طالب إضافي '.($existing + $i),
                'age_years' => 10,
                'grade_level' => '5',
                'guardian_name' => 'ولي الأمر',
                'guardian_phone' => '0500000000',
                'enrolled_at' => '2026-01-01',
                'status' => Student::STATUS_ACTIVE,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($students, 100) as $chunk) {
            DB::table('students')->insert($chunk);
        }

        $alreadyMember = DB::table('group_memberships')
            ->where('group_id', $s['group']->id)
            ->pluck('student_id')
            ->all();

        $newIds = DB::table('students')
            ->where('center_id', $s['center']->id)
            ->whereNotIn('id', $alreadyMember === [] ? [0] : $alreadyMember)
            ->pluck('id')
            ->all();

        $memberships = [];

        foreach ($newIds as $id) {
            $memberships[] = [
                'center_id' => $s['center']->id,
                'group_id' => $s['group']->id,
                'student_id' => $id,
                'joined_at' => '2026-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($memberships, 100) as $chunk) {
            DB::table('group_memberships')->insert($chunk);
        }
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

    private function review(array $s, string $date, string $pages, ?Student $student = null): Review
    {
        return Review::factory()->for($s['center'])->create([
            'student_id' => ($student ?? $s['student'])->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $date,
            'amount_pages' => $pages,
        ]);
    }

    private function recitation(array $s, string $date, string $grade, int $hifz = 0, ?Student $student = null): Recitation
    {
        return Recitation::factory()->for($s['center'])->create([
            'student_id' => ($student ?? $s['student'])->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => $date,
            'grade' => $grade,
            'hifz_errors' => $hifz,
            'tajweed_errors' => 0,
            'reading_errors' => 0,
        ]);
    }

    private function attendance(array $s, string $date, string $status, ?Student $student = null): Attendance
    {
        return Attendance::factory()->for($s['center'])->create([
            'student_id' => ($student ?? $s['student'])->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'attendance_date' => $date,
            'status' => $status,
            'late_minutes' => null,
        ]);
    }

    private function weeklyEvaluation(array $s, Student $student, string $weekKey = '2026-10-24'): WeeklyEvaluation
    {
        return WeeklyEvaluation::factory()->for($s['center'])->create([
            'student_id' => $student->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'week_key' => $weekKey,
            'week_start_date' => $weekKey,
            'week_end_date' => '2026-10-30',
        ]);
    }

    private function profileVersion(
        array $s,
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
            'rule_set' => [
                'version' => 'v1',
                'criteria' => [[
                    'key' => 'memorization_pages',
                    'weight' => '100',
                    'calculator' => 'memorization_pages',
                    'missing_data_policy' => 'block',
                    'params' => ['required_pages' => '1'],
                ]],
                'recitation_grade_scores' => [],
            ],
        ]);
    }
}
