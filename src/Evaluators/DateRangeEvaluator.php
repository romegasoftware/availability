<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Evaluators;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;
use Throwable;

final class DateRangeEvaluator implements RuleEvaluator
{
    public function matches(array $config, CarbonInterface $at, AvailabilitySubject $subject): bool
    {
        $kind = is_string($config['kind'] ?? null) ? strtolower($config['kind']) : 'absolute';
        $moment = $at->toImmutable();

        return match ($kind) {
            'yearly' => $this->matchesYearly($config, $moment),
            default => $this->matchesAbsolute($config, $moment),
        };
    }

    private function matchesYearly(array $config, CarbonImmutable $moment): bool
    {
        $timezone = $moment->timezoneName;
        $from = $this->parseYearlyBoundary($config['from'] ?? null, $timezone);
        $to = $this->parseYearlyBoundary($config['to'] ?? null, $timezone);

        if ($from === null || $to === null) {
            return false;
        }

        $current = ($moment->month * 100) + $moment->day;

        if ($from <= $to) {
            return $current >= $from && $current <= $to;
        }

        return $current >= $from || $current <= $to;
    }

    private function matchesAbsolute(array $config, CarbonImmutable $moment): bool
    {
        $timezone = $moment->timezoneName;
        $from = $this->parseAbsoluteBoundary($config['from'] ?? null, $timezone);
        $to = $this->parseAbsoluteBoundary($config['to'] ?? null, $timezone);

        if ($from === null || $to === null) {
            return false;
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return $moment->betweenIncluded($from->startOfDay(), $to->endOfDay());
    }

    private function parseYearlyBoundary(mixed $value, string $timezone): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('m-d', $value, $timezone);
        } catch (Throwable) {
            return null;
        }

        if ($date === false) {
            return null;
        }

        return ($date->month * 100) + $date->day;
    }

    private function parseAbsoluteBoundary(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $value, $timezone);
        } catch (Throwable) {
            return null;
        }

        if ($date === false) {
            return null;
        }

        return $date;
    }
}
