<?php

namespace App\Support;

use Illuminate\Support\Str;

trait GeneratesPublicId
{
    protected static function bootGeneratesPublicId(): void
    {
        static::creating(function ($model): void {
            $model->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
