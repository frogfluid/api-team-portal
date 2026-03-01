<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department',
        'preferences',
        'avatar_path',
        'is_active',
    ];

    protected $appends = [
        'avatar_url',
        'role_label',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'role' => UserRole::class,
        'department' => Department::class,
        'preferences' => 'array',
        'avatar_path' => 'string',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
            return route('app.users.avatar', $this);
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode((string) $this->name) . '&color=E32636&background=FCE8EA&rounded=true';
    }

    public function getRoleLabelAttribute(): string
    {
        if ($this->role instanceof UserRole) {
            return $this->role->label();
        }

        return ucfirst(str_replace('_', ' ', (string) $this->role));
    }

    // ── 关系 ──────────────────────────────────

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function participatedTasks()
    {
        return $this->belongsToMany(Task::class, 'task_participants');
    }

    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'channel_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    // ── 权限判断 ──────────────────────────────

    public function isManagement(): bool
    {
        return $this->role?->isManagement() ?? false;
    }

    public function isExecutive(): bool
    {
        return $this->role?->isExecutive() ?? false;
    }

    public function canReview(): bool
    {
        return $this->is_active && ($this->role?->canReview() ?? false);
    }

    public function canManageUsers(): bool
    {
        return $this->is_active && ($this->role?->canManageUsers() ?? false);
    }

    public function canViewAllData(): bool
    {
        return $this->is_active && ($this->role?->canViewAllData() ?? false);
    }

    public function canAccessAdmin(): bool
    {
        return $this->is_active && $this->role === UserRole::ADMIN;
    }

    public function canAccessApp(): bool
    {
        return $this->is_active && $this->role !== null;
    }

    public function canCreateTasks(): bool
    {
        return $this->is_active && ($this->role?->canCreateTasks() ?? false);
    }

    public function canViewAllSchedules(): bool
    {
        return $this->is_active && ($this->role?->canViewAllSchedules() ?? false);
    }

    public function hasRoleLevel(UserRole $role): bool
    {
        return ($this->role?->level() ?? 0) >= $role->level();
    }
}
