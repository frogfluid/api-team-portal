<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'leave_type',
        'all_day',
        'leave_days',
        'start_at',
        'end_at',
        'break_minutes',
        'status',
        'note',
        'repeat_group_id',
        'manager_comment',
        'manager_comment_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'all_day' => 'boolean',
        'leave_days' => 'decimal:2',
        'manager_comment_by' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function commentAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_comment_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkScheduleComment::class)->latest('created_at');
    }
}
