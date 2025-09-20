<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Contracts;

use Carbon\CarbonInterface;

interface RuleEvaluator
{
    public function matches(array $config, CarbonInterface $at, AvailabilitySubject $subject): bool;
}
