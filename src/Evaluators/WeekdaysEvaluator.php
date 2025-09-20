<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Evaluators;

use Carbon\CarbonInterface;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;

final class WeekdaysEvaluator implements RuleEvaluator
{
    public function matches(array $config, CarbonInterface $at, AvailabilitySubject $subject): bool
    {
        $days = collect($config['days'] ?? [])
            ->filter(fn ($day): bool => is_numeric($day))
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 1 && $day <= 7)
            ->unique()
            ->values()
            ->all();

        if ($days === []) {
            return false;
        }

        return in_array($at->isoWeekday(), $days, true);
    }
}
