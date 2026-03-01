<?php

namespace App\Enums;

enum Department: string
{
    case EXECUTIVE = 'executive';       // 経営企画
    case ENGINEERING = 'engineering';   // 開発部
    case DESIGN = 'design';            // デザイン部
    case MARKETING = 'marketing';      // マーケティング部
    case OPERATIONS = 'operations';    // 運用部
    case SALES = 'sales';             // 営業部
    case HR = 'hr';                   // 人事部
    case FINANCE = 'finance';         // 経理部

    public function label(): string
    {
        return match ($this) {
            self::EXECUTIVE => 'Executive Office',
            self::ENGINEERING => 'Engineering',
            self::DESIGN => 'Design',
            self::MARKETING => 'Marketing',
            self::OPERATIONS => 'Operations',
            self::SALES => 'Sales',
            self::HR => 'Human Resources',
            self::FINANCE => 'Finance',
        };
    }

    public function labelJa(): string
    {
        return match ($this) {
            self::EXECUTIVE => '経営企画',
            self::ENGINEERING => '開発部',
            self::DESIGN => 'デザイン部',
            self::MARKETING => 'マーケティング部',
            self::OPERATIONS => '運用部',
            self::SALES => '営業部',
            self::HR => '人事部',
            self::FINANCE => '経理部',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::EXECUTIVE => '👔',
            self::ENGINEERING => '⚙️',
            self::DESIGN => '🎨',
            self::MARKETING => '📣',
            self::OPERATIONS => '🔧',
            self::SALES => '💼',
            self::HR => '👥',
            self::FINANCE => '💰',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EXECUTIVE => '#DC2626',
            self::ENGINEERING => '#7C3AED',
            self::DESIGN => '#EC4899',
            self::MARKETING => '#F59E0B',
            self::OPERATIONS => '#10B981',
            self::SALES => '#3B82F6',
            self::HR => '#06B6D4',
            self::FINANCE => '#84CC16',
        };
    }
}
