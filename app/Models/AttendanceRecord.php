<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in_at',
        'clock_out_at',
        'clock_in_ip',
        'clock_out_ip',
        'work_duration_minutes',
        'status',
        'note',
        'is_manual_override',
        'is_auto_clocked_out',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'work_duration_minutes' => 'integer',
        'is_manual_override' => 'boolean',
        'is_auto_clocked_out' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($q, $id)
    {
        return $q->where('user_id', $id);
    }
    public function scopeToday($q)
    {
        return $q->where('date', now()->toDateString());
    }
    public function scopeForDate($q, $date)
    {
        return $q->where('date', $date);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'normal' => '#10B981',
            'late' => '#F59E0B',
            'early_leave' => '#EF4444',
            'absent' => '#6B7280',
            default => '#6B7280',
        };
    }

    public function getFormattedDurationAttribute(): string
    {
        $h = intdiv($this->work_duration_minutes, 60);
        $m = $this->work_duration_minutes % 60;
        if ($h > 0 && $m > 0)
            return "{$h}h {$m}m";
        return $h > 0 ? "{$h}h" : "{$m}m";
    }
}
