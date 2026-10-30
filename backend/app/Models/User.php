<?php

namespace App\Models;

use App\Domain\Identity\Models\ApiToken;
use App\Domain\Students\Models\StudentTeacherAssignment;
use App\Domain\Tenancy\Models\Center;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $center_id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property array<int, string>|null $permissions
 * @property bool $is_active
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    public const ROLE_MANAGER = 'center_manager';

    public const ROLE_TEACHER = 'teacher';

    /**
     * Default permission sets per role (docs/permissions.md §3).
     * A teacher's grants are the union of these and their own overrides.
     *
     * @var array<string, array<int, string>>
     */
    private const ROLE_PERMISSIONS = [
        self::ROLE_MANAGER => ['*'],
        self::ROLE_TEACHER => [
            'students.view',
            'memorization.record',
            'review.record',
            'recitation.record',
            'attendance.view',
            'attendance.record',
            'evaluation.view',
            'reports.view',
        ],
    ];

    protected $fillable = [
        'center_id',
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'locale',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StudentTeacherAssignment::class, 'teacher_id');
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    /**
     * Permission check. The manager holds everything; a teacher holds the role
     * defaults plus any explicit grants the manager enabled (BR-AUT-04).
     */
    public function hasPermission(string $permission): bool
    {
        $defaults = self::ROLE_PERMISSIONS[$this->role] ?? [];

        if (in_array('*', $defaults, true)) {
            return true;
        }

        if (in_array($permission, $defaults, true)) {
            return true;
        }

        return in_array($permission, $this->permissions ?? [], true);
    }

    /**
     * Issues a device-scoped token. Returns [model, plaintext].
     *
     * @return array{0: ApiToken, 1: string}
     */
    public function issueToken(string $deviceName, ?Carbon $expiresAt = null): array
    {
        $plain = Str::random(64);

        $token = $this->apiTokens()->create([
            'name' => $deviceName,
            'token_hash' => hash('sha256', $plain),
            'abilities' => ['*'],
            'expires_at' => $expiresAt ?? now()->addDays(30),
        ]);

        return [$token, $plain];
    }
}
