<?php

namespace Tests\Feature\Quran;

use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\Support\Quran\SeedsSnapshots;
use Tests\TestCase;

/**
 * quran:forget — retiring a snapshot without weakening the immutability guard.
 *
 * Accepted snapshots are archived, never deleted; pending/failed ones are
 * deleted outright. Active and referenced snapshots are refused.
 */
class QuranForgetCommandTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSnapshots;

    private function forget(int $versionId, array $options = []): PendingCommand
    {
        return $this->artisan('quran:forget', [
            'version_id' => $versionId,
            '--confirm' => (string) $versionId,
            ...$options,
        ]);
    }

    public function test_an_active_snapshot_cannot_be_retired(): void
    {
        $version = $this->seedSnapshotWithChildren('passed', $this->tinyPayload(), ['is_active' => true]);

        $this->forget($version, ['--force' => true])->assertExitCode(1);

        $this->assertSame(0, DB::table('quran_dataset_versions')->whereNotNull('archived_at')->count());
        $this->assertSame(1, DB::table('quran_dataset_versions')->where('id', $version)->count());
    }

    public function test_a_snapshot_with_historical_records_cannot_be_retired(): void
    {
        $version = $this->seedSnapshotWithChildren('passed', $this->tinyPayload());

        $center = Center::factory()->create();
        $teacher = User::factory()->teacher()->for($center)->create();
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create();

        Memorization::factory()->for($center)->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'quran_dataset_version_id' => $version,
            'quran_dataset_version' => 'test-v1',
        ]);

        $this->forget($version, ['--force' => true])->assertExitCode(1);

        $this->assertSame(0, DB::table('quran_dataset_versions')->whereNotNull('archived_at')->count());
    }

    public function test_an_accepted_snapshot_is_archived_not_deleted(): void
    {
        $version = $this->seedSnapshotWithChildren('passed', $this->tinyPayload());

        $this->forget($version, ['--force' => true, '--reason' => 'superseded'])->assertExitCode(0);

        $row = DB::table('quran_dataset_versions')->where('id', $version)->first();

        $this->assertNotNull($row);
        $this->assertNotNull($row->archived_at);
        $this->assertStringContainsString('superseded', (string) $row->notes);
        $this->assertSame(3, DB::table('quran_ayahs')->where('dataset_version_id', $version)->count());
    }

    public function test_a_failed_snapshot_is_deleted(): void
    {
        $version = $this->seedVersion('failed');

        $this->forget($version, ['--force' => true])->assertExitCode(0);

        $this->assertSame(0, DB::table('quran_dataset_versions')->where('id', $version)->count());
    }

    public function test_without_force_nothing_changes(): void
    {
        $version = $this->seedSnapshotWithChildren('passed', $this->tinyPayload());

        $this->forget($version)->assertExitCode(0);

        $this->assertNull(DB::table('quran_dataset_versions')->where('id', $version)->value('archived_at'));
    }

    public function test_the_confirmation_must_match(): void
    {
        $version = $this->seedSnapshotWithChildren('passed', $this->tinyPayload());

        $this->artisan('quran:forget', [
            'version_id' => $version,
            '--confirm' => '999',
            '--force' => true,
        ])->assertExitCode(1);

        $this->assertNull(DB::table('quran_dataset_versions')->where('id', $version)->value('archived_at'));
    }

    public function test_an_archived_snapshot_cannot_be_activated(): void
    {
        $version = $this->seedSnapshotWithChildren('passed', $this->tinyPayload());

        $this->forget($version, ['--force' => true])->assertExitCode(0);

        $this->artisan('quran:activate', ['version_id' => $version])
            ->expectsOutputToContain('archived')
            ->assertExitCode(1);
    }
}
