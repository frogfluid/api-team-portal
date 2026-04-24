<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSubmissionLate extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'iso_year', 'iso_week', 'flagged_at'];
    protected $casts = ['flagged_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeInMonth($q, int $year, int $month)
    {
        $start = \Illuminate\Support\Carbon::create($year, $month, 1);
        $end   = $start->copy()->endOfMonth();

        return $q->where(function ($q) use ($start, $end) {
            $q->whereBetween('flagged_at', [$start, $end]);
        });
    }
}
