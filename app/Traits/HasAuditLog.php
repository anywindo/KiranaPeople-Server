<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Automatic audit logging on model create/update/delete.
 * Uses boot() — attach to any Model by using this trait.
 *
 * Logs are written to the `audit_logs` table (see migration).
 * Automatically strips sensitive fields (passwords, tokens, base64 payloads).
 */
trait HasAuditLog
{
    protected static function bootHasAuditLog(): void
    {
        static::created(function ($model) {
            $after = static::filterAuditAttributes($model->getAttributes(), $model);
            static::writeAudit('created', $model, [], $after);
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            $filteredChanges = static::filterAuditAttributes($changes, $model);

            // If only ignored attributes (e.g. updated_at, token) changed, skip logging
            if (empty($filteredChanges)) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $filteredChanges);
            $before = static::filterAuditAttributes($original, $model);
            $after = $filteredChanges;

            static::writeAudit('updated', $model, $before, $after);
        });

        static::deleted(function ($model) {
            $before = static::filterAuditAttributes($model->getAttributes(), $model);
            static::writeAudit('deleted', $model, $before, []);
        });
    }

    /**
     * Filter out sensitive or irrelevant attributes.
     */
    protected static function filterAuditAttributes(array $attributes, $model): array
    {
        $defaultIgnored = [
            'password',
            'remember_token',
            'avatar_base64',
            'selfie_base64',
            'updated_at',
        ];

        $modelIgnored = property_exists($model, 'auditIgnore') && is_array($model->auditIgnore)
            ? $model->auditIgnore
            : [];

        $allIgnored = array_merge($defaultIgnored, $modelIgnored);

        return array_diff_key($attributes, array_flip($allIgnored));
    }

    protected static function writeAudit(string $event, $model, array $before, array $after): void
    {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'event' => $event,
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'before' => ! empty($before) ? $before : null,
                'after' => ! empty($after) ? $after : null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Fallback: log to file if audit_logs table or connection encounters an issue
            Log::channel('daily')->warning(
                "[AuditLog] {$event} on ".get_class($model)." #{$model->getKey()}: ".$e->getMessage()
            );
        }
    }
}
