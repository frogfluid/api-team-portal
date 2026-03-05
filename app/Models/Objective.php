<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Objective extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'period',
        'department',
        'owner_id',
        'parent_id',
        'status',
        'progress',
    ];

    protected $casts = ['progress' => 'integer'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Objective::class, 'parent_id');
    }
    public function children(): HasMany
    {
        return $this->hasMany(Objective::class, 'parent_id');
    }
    public function keyResults(): HasMany
    {
        return $this->hasMany(KeyResult::class)->orderBy('sort_order');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }
    public function scopeForPeriod($q, $p)
    {
        return $q->where('period', $p);
    }
    public function scopeForOwner($q, $id)
    {
        return $q->where('owner_id', $id);
    }
    public function scopeRoots($q)
    {
        return $q->whereNull('parent_id');
    }

    public function recalculateProgress(): void
    {
        $krs = $this->keyResults;
        if ($krs->isEmpty()) {
            $this->update(['progress' => 0]);
            return;
        }
        $total = $krs->sum('weight');
        if ($total === 0) {
            $this->update(['progress' => 0]);
            return;
        }
        $this->update(['progress' => (int) round($krs->sum(fn($kr) => $kr->progress * $kr->weight) / $total)]);
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'company' => '#DC2626', 'team' => '#6366F1', 'personal' => '#10B981', default => '#6B7280',
        };
    }

    public static function currentPeriod(): string
    {
        return 'Q' . ceil(now()->month / 3) . '-' . now()->year;
    }
}
