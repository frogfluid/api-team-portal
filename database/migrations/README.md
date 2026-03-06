# ⚠️ Migration 管理说明

## 请勿在此目录创建新的 migration

**所有数据库结构变更（migration）统一在 Web Portal 项目管理：**

```
team-samuraiplus/database/migrations/
```

## 原因

API 项目 (`api-team-portal`) 和 Web Portal 项目 (`team-samuraiplus`) 共享同一个数据库 `team_samurai_plus`。
为避免两边 migration 不同步导致混乱，约定：

- ✅ **Web Portal** 负责创建和运行所有 migration
- ❌ **API 项目** 不创建新 migration，不运行 `php artisan migrate`
- 📁 此目录的文件保留作为历史参考，但已冻结

## 如果需要改数据库

1. 在 `team-samuraiplus` 项目中创建 migration
2. 在 `team-samuraiplus` 项目中运行 `php artisan migrate`
3. 无需在 API 端做任何操作

> 生效日期：2026-03-06
