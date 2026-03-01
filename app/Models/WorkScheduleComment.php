<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkScheduleComment extends Model
{
    protected $fillable = [
        'work_schedule_id',
        'user_id',
        'body',
        'mentioned_user_ids',
    ];

    protected $casts = [
        'mentioned_user_ids' => 'array',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
