<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'status',
        'priority',
        'owner_id',
        'created_by',
        'start_date',
        'target_date',
        'progress',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_date' => 'date',
        'progress' => 'integer',
    ];

    // ── Relations ──────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    // ── Query Scopes ──────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('owner_id', $userId)
                ->orWhere('created_by', $userId)
                ->orWhereHas('members', fn($m) => $m->where('users.id', $userId));
        });
    }

    // ── Helpers ──────────────────────────────

    public function recalculateProgress(): void
    {
        $tasks = $this->tasks;
        if ($tasks->isEmpty()) {
            $this->update(['progress' => 0]);
            return;
        }
        $totalProgress = $tasks->sum('progress');
        $avgProgress = (int) round($totalProgress / $tasks->count());
        $this->update(['progress' => $avgProgress]);
    }

    public function getTaskStatsAttribute(): array
    {
        $tasks = $this->tasks;
        return [
            'total' => $tasks->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'blocked' => $tasks->where('status', 'blocked')->count(),
            'todo' => $tasks->whereIn('status', ['pending', 'open'])->count(),
        ];
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => '#DC2626',
            'high' => '#F59E0B',
            'medium' => '#6366F1',
            'low' => '#6B7280',
            default => '#6366F1',
        };
    }

    public function isMember(int $userId): bool
    {
        return $this->owner_id === $userId
            || $this->created_by === $userId
            || $this->members()->where('users.id', $userId)->exists();
    }
}
