<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year_month',
        'base_salary',
        'bonus',
        'allowance',
        'overtime',
        'deduction',
        'deduction_socso',
        'deduction_eis',
        'deduction_eps',
        'deduction_pcb',
        'other_deduction',
        'net_amount',
        'payment_date',
        'currency',
        'note',
        'status',
        'actual_work_days',
        'calculated_base_salary',
        'notified_at',
    ];

    protected $casts = [
        'base_salary' => 'float',
        'bonus' => 'float',
        'allowance' => 'float',
        'overtime' => 'float',
        'deduction' => 'float',
        'deduction_socso' => 'float',
        'deduction_eis' => 'float',
        'deduction_eps' => 'float',
        'deduction_pcb' => 'float',
        'other_deduction' => 'float',
        'net_amount' => 'float',
        'actual_work_days' => 'integer',
        'calculated_base_salary' => 'float',
        'payment_date' => 'date',
        'notified_at' => 'datetime',
    ];

    /**
     * Runtime-attached monthly stats from PayrollComputationService.
     * Used by the admin payroll view only; not persisted.
     *
     * @var array<string, mixed>|null
     */
    public ?array $monthlyStats = null;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Policy v2.1: hourly_rate = base_salary / 120 (derived, no DB column).
     */
    public function getHourlyRateAttribute(): float
    {
        $base = (float) $this->base_salary;
        return $base > 0 ? round($base / 120.0, 4) : 0.0;
    }

    public function getGrossAmountAttribute(): float
    {
        return round(
            (float) $this->base_salary + (float) $this->bonus + (float) $this->allowance + (float) $this->overtime,
            2
        );
    }

    public function getTotalDeductionAttribute(): float
    {
        return round(
            (float) $this->deduction + (float) $this->deduction_socso + (float) $this->deduction_eis + (float) $this->deduction_eps + (float) $this->deduction_pcb + (float) $this->other_deduction,
            2
        );
    }

    /**
     * Auto-calculate net pay amount
     */
    public function calculateNetAmount(): float
    {
        return round(
            $this->gross_amount - $this->total_deduction,
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
