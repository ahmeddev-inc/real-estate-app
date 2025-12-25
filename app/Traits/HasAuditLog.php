<?php

namespace App\Traits;

trait HasAuditLog
{
    public static function bootHasAuditLog()
    {
        static::created(function ($model) {
            $model->logAudit('created', $model->toArray());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            if (!empty($changes)) {
                $model->logAudit('updated', $changes, $model->getOriginal());
            }
        });

        static::deleted(function ($model) {
            $model->logAudit('deleted', $model->toArray());
        });
    }

    protected function logAudit($action, $newData = [], $oldData = [])
    {
        // سيتم تنفيذ هذا لاحقاً مع نموذج AuditLog
    }
}
