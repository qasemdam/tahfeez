<?php

namespace Tests\Feature\Api;

use App\Domain\Students\Actions\AssignStudentToTeacher;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentApiTest extends TestCase
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

    public function test_a_guest_cannot_list_students(): void
    {
        $this->getJson('/api/v1/students')->assertUnauthorized();
    }

    public function test_a_manager_sees_every_student_in_the_center(): void
    {
        $center = Center::factory()->create();
        $manager = User::factory()->manager()->for($center)->create();
        Student::factory()->count(2)->for($center)->create();
        Student::factory()->create(); // belongs to another center

        $this->withHeaders($this->auth($manager))
            ->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_teacher_sees_only_assigned_students(): void
    {
        $center = Center::factory()->create();
        $teacher = User::factory()->teacher()->for($center)->create();
        $otherTeacher = User::factory()->teacher()->for($center)->create();

        $assigned = Student::factory()->for($center)->create(['full_name' => 'Assigned One']);
        $unassigned = Student::factory()->for($center)->create(['full_name' => 'Unassigned Two']);

        app(AssignStudentToTeacher::class)->execute($assigned, $teacher, $teacher);
        app(AssignStudentToTeacher::class)->execute($unassigned, $otherTeacher, $otherTeacher);

        $this->withHeaders($this->auth($teacher))
            ->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);
    }

    public function test_a_manager_can_enroll_a_student_and_assign_a_teacher(): void
    {
        $center = Center::factory()->create();
        $manager = User::factory()->manager()->for($center)->create();
        $teacher = User::factory()->teacher()->for($center)->create();

        $response = $this->withHeaders($this->auth($manager))->postJson('/api/v1/students', [
            'full_name' => 'أحمد محمد',
            'guardian_name' => 'محمد علي',
            'guardian_phone' => '0555555555',
            'enrolled_at' => '2026-09-01',
            'teacher_id' => $teacher->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.full_name', 'أحمد محمد')
            ->assertJsonPath('data.teacher_name', $teacher->name);

        $this->assertDatabaseHas('students', [
            'center_id' => $center->id,
            'full_name' => 'أحمد محمد',
        ]);
        $this->assertDatabaseHas('student_teacher_assignments', [
            'teacher_id' => $teacher->id,
            'unassigned_at' => null,
        ]);
    }

    public function test_a_teacher_cannot_enroll_a_student(): void
    {
        $center = Center::factory()->create();
        $teacher = User::factory()->teacher()->for($center)->create();

        $this->withHeaders($this->auth($teacher))->postJson('/api/v1/students', [
            'full_name' => 'طالب',
            'guardian_name' => 'ولي الأمر',
            'enrolled_at' => '2026-09-01',
        ])->assertForbidden();
    }

    public function test_search_filters_by_name(): void
    {
        $center = Center::factory()->create();
        $manager = User::factory()->manager()->for($center)->create();
        Student::factory()->for($center)->create(['full_name' => 'سعيد']);
        Student::factory()->for($center)->create(['full_name' => 'خالد']);

        $this->withHeaders($this->auth($manager))
            ->getJson('/api/v1/students?search=سعيد')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'سعيد');
    }

    public function test_an_out_of_scope_student_returns_404_not_403(): void
    {
        $center = Center::factory()->create();
        $teacher = User::factory()->teacher()->for($center)->create();
        $student = Student::factory()->for($center)->create();

        $this->withHeaders($this->auth($teacher))
            ->getJson('/api/v1/students/'.$student->id)
            ->assertNotFound();
    }
}
