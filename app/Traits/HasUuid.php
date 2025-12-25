<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    protected static function bootHasUuid()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::orderedUuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public static function findByUuid($uuid)
    {
        return static::where('uuid', $uuid)->first();
    }

    public function scopeWhereUuid($query, $uuid)
    {
        return $query->where('uuid', $uuid);
    }
}
