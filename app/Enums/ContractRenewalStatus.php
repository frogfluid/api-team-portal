<?php

namespace App\Enums;

enum ContractRenewalStatus: string
{
    case PENDING_DISCUSSION = 'pending_discussion';
    case RENEWED = 'renewed';
    case ENDING = 'ending';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_DISCUSSION => 'Pending Discussion',
            self::RENEWED => 'Renewed',
            self::ENDING => 'Ending',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING_DISCUSSION => 'border-amber-200 bg-amber-50 text-amber-700',
            self::RENEWED => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            self::ENDING => 'border-rose-200 bg-rose-50 text-rose-700',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::PENDING_DISCUSSION;
    }
}
