<?php

declare(strict_types=1);

namespace App\Utility\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::generateSlug($model);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty(static::getSlugSourceField()) && empty($model->slug)) {
                $model->slug = static::generateSlug($model);
            }
        });
    }

    protected static function getSlugSourceField(): string
    {
        return 'name';
    }

    protected static function generateSlug($model): string
    {
        $sourceField = static::getSlugSourceField();
        $sourceValue = $model->{$sourceField} ?? '';

        if (empty($sourceValue)) {
            return '';
        }

        $slug = Str::slug($sourceValue);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $model->id ?? 0)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function findBySlug(string $slug)
    {
        return static::where('slug', $slug)->first();
    }

    public static function findBySlugOrFail(string $slug)
    {
        return static::where('slug', $slug)->firstOrFail();
    }
}

