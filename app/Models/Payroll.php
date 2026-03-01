<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $fillable = [
        'user_id',
        'year_month',
        'base_salary',
        'bonus',
        'allowance',
        'deduction',
        'net_amount',
        'payment_date',
        'currency',
        'note',
        'status',
        'notified_at',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'bonus' => 'decimal:2',
        'allowance' => 'decimal:2',
        'deduction' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'payment_date' => 'date',
        'notified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 自动计算实发金额
     */
    public function calculateNetAmount(): float
    {
        return round(
            (float) $this->base_salary + (float) $this->bonus + (float) $this->allowance - (float) $this->deduction,
            2
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'published' => 'Published',
            'paid' => 'Paid',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-600',
            'published' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-100',
            'paid' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
            default => 'bg-gray-100 text-gray-600',
        };
    }
}
