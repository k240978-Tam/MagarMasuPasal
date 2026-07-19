<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Stamps a sortable, non-guessable ULID into `public_id` on create, for use
 * in URLs/API/receipts instead of the internal auto-increment `id`.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
