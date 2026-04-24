<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyMessage extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'user_id',
        'author_id',
        'target_month',
        'review',
        'goals',
        'confirmed_at',
        'response',
    ];

    protected $casts = [
        'goals' => 'array',
        'target_month' => 'date',
        'confirmed_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MonthlyMessageComment::class)->orderBy('created_at');
    }

    // ── Scopes ──────────────────────────────────────

    public function scopeUnconfirmed($query)
    {
        return $query->whereNull('confirmed_at');
    }

    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->where('target_month', $month);
    }

    // ── Helpers ──────────────────────────────────────

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function getTargetMonthLabelAttribute(): string
    {
        return $this->target_month->format('F Y');
    }

    public function getStatusBadgeAttribute(): array
    {
        if ($this->isConfirmed()) {
            return [
                'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400',
                'label' => __('Confirmed'),
            ];
        }

        return [
            'class' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100 dark:bg-amber-900/20 dark:text-amber-400',
            'label' => __('Pending'),
        ];
    }
}
