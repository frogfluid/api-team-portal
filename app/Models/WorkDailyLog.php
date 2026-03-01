<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkDailyLog extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'started_at',
        'ended_at',
        'break_minutes',
        'worked_minutes',
        'note',
        'status',
        'submitted_at',
        'admin_comment'
    ];

    protected $casts = [
        'work_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
