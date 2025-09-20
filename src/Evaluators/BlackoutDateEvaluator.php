<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Evaluators;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;
use Throwable;

final class BlackoutDateEvaluator implements RuleEvaluator
{
    public function matches(array $config, CarbonInterface $at, AvailabilitySubject $subject): bool
    {
        return collect($config['dates'] ?? [])
            ->filter(fn ($date): bool => is_string($date) && $date !== '')
            ->map(function (string $date) use ($at): ?string {
                try {
                    $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date, $at->timezoneName);
                } catch (Throwable) {
                    return null;
                }

                if ($parsed === false) {
                    return null;
                }

                return $parsed->toDateString();
            })
            ->filter()
            ->unique()
            ->values()
            ->containsStrict($at->toImmutable()->toDateString());
    }
}
