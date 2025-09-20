<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RomegaSoftware\Availability\Support\Effect;

interface AvailabilitySubject
{
    public function availabilityRules(): MorphMany;

    public function getAvailabilityDefaultEffect(): Effect;

    public function getAvailabilityTimezone(): ?string;
}
