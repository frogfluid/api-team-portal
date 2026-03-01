<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'location',
        'status',
        'priority',
        'progress',
        'due_at',
        'created_by',
        'owner_id',
        'last_activity_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_participants')
            ->withPivot(['role', 'completed_at'])
            ->withTimestamps();
    }

    public function scopeMine($q, int $userId)
    {
        return $q->where(function ($qq) use ($userId) {
            $qq->where('created_by', $userId)
                ->orWhere('owner_id', $userId)
                ->orWhereHas('participants', fn($p) => $p->where('users.id', $userId));
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TaskMessage::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function ownerHistories(): HasMany
    {
        return $this->hasMany(TaskOwnerHistory::class)->latest('changed_at');
    }

    public function touchActivity(?\DateTimeInterface $at = null): void
    {
        $this->forceFill([
            'last_activity_at' => $at ?? now(),
        ])->save();
    }
}
