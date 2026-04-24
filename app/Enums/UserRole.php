<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case MEMBER = 'member';
    // INTERN removed — migrated to MEMBER per migration 2026_04_21_100000.

    /**
     * 显示名称
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::MANAGER => 'System Manager',
            self::MEMBER => 'Member',
        };
    }

    /**
     * 权限级别（数值越大权限越高）
     */
    public function level(): int
    {
        return match ($this) {
            self::ADMIN => 100,
            self::MANAGER => 60,
            self::MEMBER => 40,
        };
    }

    /**
     * 角色分组标签
     */
    public function tierLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Executive',
            self::MANAGER => 'Management',
            self::MEMBER => 'Employee',
        };
    }

    /**
     * 是否为高管
     */
    public function isExecutive(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * 是否为管理层（Manager 及以上）
     */
    public function isManagement(): bool
    {
        return $this->level() >= self::MANAGER->level();
    }

    /**
     * 是否为正式雇员（Member 及以上）
     */
    public function isFullEmployee(): bool
    {
        return $this->level() >= self::MEMBER->level();
    }

    /**
     * 能否审批日报/周报（Manager 及以上）
     */
    public function canReview(): bool
    {
        return $this->isManagement();
    }

    /**
     * 能否管理用户账号（Admin）
     */
    public function canManageUsers(): bool
    {
        return $this->isExecutive();
    }

    /**
     * 能否查看全公司数据（Manager 及以上）
     */
    public function canViewAllData(): bool
    {
        return $this->isManagement();
    }

    /**
     * 能否创建/分配任务
     */
    public function canCreateTasks(): bool
    {
        return $this->isFullEmployee();
    }

    /**
     * 能否查看排班日历中所有人（Manager 及以上）
     */
    public function canViewAllSchedules(): bool
    {
        return $this->isManagement();
    }

    /**
     * 角色 UI 颜色
     */
    public function color(): string
    {
        return match ($this) {
            self::ADMIN => '#DC2626',
            self::MANAGER => '#0891B2',
            self::MEMBER => '#059669',
        };
    }

    /**
     * 角色 Badge CSS class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::ADMIN => 'bg-red-50 text-red-700 border-red-100 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800/50',
            self::MANAGER => 'bg-cyan-50 text-cyan-700 border-cyan-100 dark:bg-cyan-900/20 dark:text-cyan-400 dark:border-cyan-800/50',
            self::MEMBER => 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800/50',
        };
    }
}
