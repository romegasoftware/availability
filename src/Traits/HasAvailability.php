<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RomegaSoftware\Availability\Models\AvailabilityRule;
use RomegaSoftware\Availability\Support\Effect;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasAvailability
{
    public function availabilityRules(): MorphMany
    {
        /** @var class-string<AvailabilityRule> $ruleModel */
        $ruleModel = config('availability.models.rule', AvailabilityRule::class);

        return $this->morphMany($ruleModel, 'subject');
    }

    public function getAvailabilityDefaultEffect(): Effect
    {
        $value = $this->getAttribute('availability_default');

        if ($value instanceof Effect) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return Effect::from($value);
        }

        $configDefault = config('availability.default_effect', Effect::Allow->value);

        return Effect::from($configDefault);
    }

    public function getAvailabilityTimezone(): ?string
    {
        $timezone = $this->getAttribute('availability_timezone');

        if (is_string($timezone) && $timezone !== '') {
            return $timezone;
        }

        return null;
    }
}
