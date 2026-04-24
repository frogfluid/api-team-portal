<?php

namespace App\Traits;

use App\Models\AuditLog;

/**
 * Trait Auditable
 *
 * Automatically logs created, updated, and deleted events to the audit_logs table.
 * Apply this trait to any Model that needs audit tracking.
 *
 * Optionally define $auditExclude on the model to skip certain fields:
 *   protected array $auditExclude = ['password', 'remember_token'];
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->logAudit('created', [], $model->getAuditableAttributes());
        });

        static::updated(function ($model) {
            $changed = $model->getChanges();
            $original = array_intersect_key($model->getOriginal(), $changed);

            // Filter out excluded fields
            $exclude = property_exists($model, 'auditExclude') ? $model->auditExclude : [];
            $exclude = array_merge($exclude, ['updated_at', 'created_at']);

            $changed = array_diff_key($changed, array_flip($exclude));
            $original = array_diff_key($original, array_flip($exclude));

            if (empty($changed)) {
                return;
            }

            $model->logAudit('updated', $original, $changed);
        });

        static::deleted(function ($model) {
            $model->logAudit('deleted', $model->getAuditableAttributes(), []);
        });
    }

    protected function logAudit(string $action, array $oldValues, array $newValues): void
    {
        $userId = auth()->id();

        if (!$userId) {
            return; // Skip audit if no authenticated user (e.g., console commands)
        }

        try {
            AuditLog::create([
                'user_id' => $userId,
                'action' => $action,
                'auditable_type' => static::class,
                'auditable_id' => $this->getKey(),
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Silently fail — audit logging should never break the main flow
            report($e);
        }
    }

    protected function getAuditableAttributes(): array
    {
        $attributes = $this->attributesToArray();
        $exclude = property_exists($this, 'auditExclude') ? $this->auditExclude : [];
        $exclude = array_merge($exclude, ['updated_at', 'created_at', 'password', 'remember_token']);

        return array_diff_key($attributes, array_flip($exclude));
    }
}
