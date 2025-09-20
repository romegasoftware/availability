<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RomegaSoftware\Availability\Support\Effect;

/**
 * @property array<string, mixed>|null $config
 */
final class AvailabilityRule extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('availability.table');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority');
    }

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'effect' => Effect::class,
            'enabled' => 'bool',
        ];
    }
}
