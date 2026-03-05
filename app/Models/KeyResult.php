<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeyResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'objective_id',
        'title',
        'metric_type',
        'start_value',
        'target_value',
        'current_value',
        'weight',
        'sort_order',
    ];

    protected $casts = [
        'start_value' => 'decimal:2',
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'weight' => 'integer',
        'sort_order' => 'integer',
    ];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }
    public function checkIns(): HasMany
    {
        return $this->hasMany(OkrCheckIn::class)->latest('created_at');
    }

    public function getProgressAttribute(): int
    {
        if ($this->metric_type === 'boolean')
            return $this->current_value >= 1 ? 100 : 0;
        $range = $this->target_value - $this->start_value;
        if ($range == 0)
            return $this->current_value >= $this->target_value ? 100 : 0;
        return (int) min(100, max(0, round(($this->current_value - $this->start_value) / $range * 100)));
    }
}
