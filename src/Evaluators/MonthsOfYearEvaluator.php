<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Evaluators;

use Carbon\CarbonInterface;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;

final class MonthsOfYearEvaluator implements RuleEvaluator
{
    public function matches(array $config, CarbonInterface $at, AvailabilitySubject $subject): bool
    {
        $months = collect($config['months'] ?? [])
            ->filter(fn ($month): bool => is_numeric($month))
            ->map(fn ($month): int => (int) $month)
            ->all();

        if ($months === []) {
            return false;
        }

        return in_array((int) $at->month, $months, true);
    }
}
