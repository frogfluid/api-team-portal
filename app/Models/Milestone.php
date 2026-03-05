<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'due_date',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'due_date' => 'date',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->status === 'open'
            && $this->due_date->isPast();
    }

    public function getProgressAttribute(): int
    {
        $tasks = $this->tasks;
        if ($tasks->isEmpty())
            return 0;

        return (int) round($tasks->sum('progress') / $tasks->count());
    }
}
