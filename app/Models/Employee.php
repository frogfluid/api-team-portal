<?php

namespace App\Models;

use App\Enums\ContractRenewalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'legal_name',
        'employee_no',
        'employment_type',
        'department_id',
        'date_of_birth',
        'phone',
        'address',
        'nationality',
        'joined_on',
        'left_on',
        'contract_start_date',
        'contract_end_date',
        'contract_renewal_status',
        'contract_review_meeting_at',
        'contract_reviewed_at',
        'contract_review_notes',
        'contract_hours_per_day',
        'contract_hours_per_week',
        'status',
        'note',
    ];

    protected $casts = [
        'date_of_birth'       => 'date',
        'joined_on'           => 'date',
        'left_on'             => 'date',
        'contract_start_date' => 'date',
        'contract_end_date'   => 'date',
        'contract_renewal_status' => ContractRenewalStatus::class,
        'contract_review_meeting_at' => 'datetime',
        'contract_reviewed_at' => 'datetime',
        'contract_hours_per_day' => 'decimal:2',
        'contract_hours_per_week' => 'decimal:2',
    ];

    /**
     * Returns: 'none' | 'active' | 'expiring_soon' | 'expired'
     * Uses AppSetting::get('contract_expiry_alert_days', 30) for threshold.
     */
    public function getContractStatusAttribute(): string
    {
        if (!$this->contract_end_date) {
            return 'none';
        }

        $today = now()->startOfDay();
        $end   = $this->contract_end_date->startOfDay();

        if ($end->lt($today)) {
            return 'expired';
        }

        $alertDays = (int) (\App\Models\AppSetting::get('contract_expiry_alert_days', 30));
        $daysLeft  = (int) $today->diffInDays($end); // positive when end is in future
        if ($daysLeft <= $alertDays) {
            return 'expiring_soon';
        }

        return 'active';
    }

    public function getDaysUntilContractExpiryAttribute(): ?int
    {
        if (!$this->contract_end_date) {
            return null;
        }
        $today = now()->startOfDay();
        $end   = $this->contract_end_date->startOfDay();
        return (int) $today->diffInDays($end, false);
    }

    public function getResolvedContractRenewalStatusAttribute(): ?ContractRenewalStatus
    {
        if ($this->contract_renewal_status instanceof ContractRenewalStatus) {
            return $this->contract_renewal_status;
        }

        if (is_string($this->attributes['contract_renewal_status'] ?? null)) {
            return ContractRenewalStatus::tryFrom($this->attributes['contract_renewal_status']);
        }

        if (in_array($this->contract_status, ['expiring_soon', 'expired'], true)) {
            return ContractRenewalStatus::PENDING_DISCUSSION;
        }

        return null;
    }

    public function getResolvedContractRenewalStatusLabelAttribute(): ?string
    {
        return $this->resolved_contract_renewal_status?->label();
    }

    public function getShouldNotifyContractExpiryAttribute(): bool
    {
        if (!$this->contract_end_date || !$this->user) {
            return false;
        }

        if ($this->contract_end_date->isPast()) {
            return $this->resolved_contract_renewal_status === ContractRenewalStatus::PENDING_DISCUSSION;
        }

        $resolvedStatus = $this->resolved_contract_renewal_status;

        return $resolvedStatus === null || !$resolvedStatus->isFinal();
    }

    public function getEffectiveContractHoursPerDayAttribute(): ?float
    {
        if ($this->contract_hours_per_day !== null) {
            return round((float) $this->contract_hours_per_day, 2);
        }

        if ($this->contract_hours_per_week !== null) {
            return round((float) $this->contract_hours_per_week / 5, 2);
        }

        return null;
    }

    public function getEffectiveContractHoursPerWeekAttribute(): ?float
    {
        if ($this->contract_hours_per_week !== null) {
            return round((float) $this->contract_hours_per_week, 2);
        }

        if ($this->contract_hours_per_day !== null) {
            return round((float) $this->contract_hours_per_day * 5, 2);
        }

        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
