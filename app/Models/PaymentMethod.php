<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'method_type',
        'bank_name',
        'branch_name',
        'account_type',
        'account_number',
        'account_holder',
        'swift_bic',
        'email',
        'extra_info',
        'is_default',
    ];

    protected $casts = [
        'extra_info' => 'array',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getMethodTypeLabelAttribute(): string
    {
        return match ($this->method_type) {
            'bank_transfer' => 'Bank Transfer',
            'tng' => 'Touch \'n Go (TNG)',
            default => ucfirst(str_replace('_', ' ', $this->method_type)),
        };
    }

    /**
     * Masked account number (show last 4 digits only)
     */
    public function getMaskedAccountNumberAttribute(): string
    {
        if (!$this->account_number) {
            return '';
        }
        $len = strlen($this->account_number);
        if ($len <= 4) {
            return $this->account_number;
        }
        return str_repeat('•', $len - 4) . substr($this->account_number, -4);
    }
}
