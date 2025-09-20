<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Evaluators;

use Carbon\CarbonInterface;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;

final class TimeOfDayEvaluator implements RuleEvaluator
{
    public function matches(array $config, CarbonInterface $at, AvailabilitySubject $subject): bool
    {
        $from = $this->parseTimeToSeconds($config['from'] ?? null);
        $to = $this->parseTimeToSeconds($config['to'] ?? null);

        if ($from === null || $to === null) {
            return false;
        }

        $seconds = ($at->hour * 3600) + ($at->minute * 60) + $at->second;

        if ($from === $to) {
            return true;
        }

        if ($from < $to) {
            return $seconds >= $from && $seconds <= $to;
        }

        return $seconds >= $from || $seconds <= $to;
    }

    private function parseTimeToSeconds(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (! preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $second = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return ($hour * 3600) + ($minute * 60) + $second;
    }
}
