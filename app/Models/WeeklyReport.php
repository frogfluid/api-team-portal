<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReport extends Model
{
    protected $fillable = [
        'user_id',
        'week_start_date',
        'summary',
        'achievements',
        'issues',
        'next_week_plan',
        'ideas',
        'support_needed',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'week_start_date' => 'date',
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
