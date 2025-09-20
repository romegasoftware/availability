<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Evaluators;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;
use Throwable;

final class RRuleEvaluator implements RuleEvaluator
{
    public function matches(array $config, CarbonInterface $at, AvailabilitySubject $subject): bool
    {
        $rule = is_string($config['rrule'] ?? null) ? trim($config['rrule']) : '';

        if ($rule === '') {
            return false;
        }

        $components = $this->parseRule($rule);

        if ($components === []) {
            return false;
        }

        $moment = $at->toImmutable();
        $timezone = is_string($config['tz'] ?? null) && $config['tz'] !== ''
            ? $config['tz']
            : $moment->timezoneName;
        $moment = $moment->setTimezone($timezone);

        return $this->matchesComponents($components, $moment);
    }

    /**
     * @return array<string, string>
     */
    private function parseRule(string $rule): array
    {
        $components = [];

        foreach (preg_split('/;/', $rule, -1, PREG_SPLIT_NO_EMPTY) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $key = strtoupper(trim($key));
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            $components[$key] = $value;
        }

        return $components;
    }

    /**
     * @param  array<string, string>  $components
     */
    private function matchesComponents(array $components, CarbonImmutable $moment): bool
    {
        $frequency = strtoupper($components['FREQ'] ?? '');

        if ($frequency === '') {
            return false;
        }

        if (! $this->matchesUntil($components, $moment)) {
            return false;
        }

        if (! $this->matchesInterval($components, $moment, $frequency)) {
            return false;
        }

        if (! $this->matchesByMonth($components, $moment)) {
            return false;
        }

        if (! $this->matchesByMonthDay($components, $moment)) {
            return false;
        }

        if (! $this->matchesByDay($components, $moment, $frequency)) {
            return false;
        }

        if (! $this->matchesByTime($components, $moment)) {
            return false;
        }

        return match ($frequency) {
            'DAILY' => true,
            'WEEKLY' => $this->matchesWeekly($components),
            'MONTHLY' => $this->matchesMonthly($components, $moment),
            'YEARLY' => $this->matchesYearly($components, $moment),
            default => false,
        };
    }

    /**
     * @param  array<string, string>  $components
     */
    private function matchesUntil(array $components, CarbonImmutable $moment): bool
    {
        if (! isset($components['UNTIL'])) {
            return true;
        }

        $until = $this->parseDateTime($components['UNTIL'], $moment->timezoneName);

        if ($until === null) {
            return false;
        }

        return $moment->lessThanOrEqualTo($until);
    }

    /**
     * @param  array<string, string>  $components
     */
    private function matchesInterval(array $components, CarbonImmutable $moment, string $frequency): bool
    {
        $interval = isset($components['INTERVAL']) ? (int) $components['INTERVAL'] : 1;
        $interval = max($interval, 1);

        if ($interval === 1) {
            return true;
        }

        $start = $this->parseDateTime($components['DTSTART'] ?? null, $moment->timezoneName);

        if ($start === null || $start->greaterThan($moment)) {
            return false;
        }

        return match ($frequency) {
            'DAILY' => $start->diffInDays($moment) % $interval === 0,
            'WEEKLY' => $start->startOfWeek()->diffInWeeks($moment->startOfWeek()) % $interval === 0,
            'MONTHLY' => $start->diffInMonths($moment) % $interval === 0,
            'YEARLY' => $start->diffInYears($moment) % $interval === 0,
            default => false,
        };
    }

    /**
     * @param  array<string, string>  $components
     */
    private function matchesByMonth(array $components, CarbonImmutable $moment): bool
    {
        if (! isset($components['BYMONTH'])) {
            return true;
        }

        $months = $this->parseIntList($components['BYMONTH'], 1, 12);

        if ($months === []) {
            return false;
        }

        return in_array($moment->month, $months, true);
    }

    /**
     * @param  array<string, string>  $components
     */
    private function matchesByMonthDay(array $components, CarbonImmutable $moment): bool
    {
        if (! isset($components['BYMONTHDAY'])) {
            return true;
        }

        $days = $this->parseIntList($components['BYMONTHDAY'], -31, 31);
        $days = array_filter($days, fn (int $day): bool => $day !== 0);

        if ($days === []) {
            return false;
        }

        $currentDay = $moment->day;
        $daysInMonth = $moment->daysInMonth;

        foreach ($days as $day) {
            if ($day > 0 && $currentDay === $day) {
                return true;
            }

            if ($day < 0) {
                $target = $daysInMonth + $day + 1;

                if ($target > 0 && $currentDay === $target) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $components
     */
    private function matchesByDay(array $components, CarbonImmutable $moment, string $frequency): bool
    {
        if (! isset($components['BYDAY'])) {
            return true;
        }

        $constraints = $this->parseByDayList($components['BYDAY']);

        if ($constraints === []) {
            return false;
        }

        foreach ($constraints as $constraint) {
            if ($moment->isoWeekday() !== $constraint['weekday']) {
                continue;
            }

            $ordinal = $constraint['ordinal'];

            if ($ordinal === null) {
                return true;
            }

            if ($frequency === 'MONTHLY' && $this->isNthWeekdayOfMonth($moment, $ordinal)) {
                return true;
            }

            if ($frequency === 'YEARLY' && $this->isNthWeekdayOfYear($moment, $ordinal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $components
     */
    private function matchesByTime(array $components, CarbonImmutable $moment): bool
    {
        if (isset($components['BYHOUR'])) {
            $hours = $this->parseIntList($components['BYHOUR'], 0, 23);

            if ($hours === [] || ! in_array($moment->hour, $hours, true)) {
                return false;
            }
        }

        if (isset($components['BYMINUTE'])) {
            $minutes = $this->parseIntList($components['BYMINUTE'], 0, 59);

            if ($minutes === [] || ! in_array($moment->minute, $minutes, true)) {
                return false;
            }
        }

        if (isset($components['BYSECOND'])) {
            $seconds = $this->parseIntList($components['BYSECOND'], 0, 59);

            if ($seconds === [] || ! in_array($moment->second, $seconds, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $components
     */
    private function matchesWeekly(array $components): bool
    {
        // Weekly rules rely on BYDAY filtering which is already applied.
        return true;
    }

    /**
     * @param  array<string, string>  $components
     */
    private function matchesMonthly(array $components, CarbonImmutable $moment): bool
    {
        if (isset($components['BYMONTHDAY']) || isset($components['BYDAY'])) {
            return true;
        }

        $start = $this->parseDateTime($components['DTSTART'] ?? null, $moment->timezoneName);

        if ($start === null) {
            return false;
        }

        return $start->day === $moment->day;
    }

    /**
     * @param  array<string, string>  $components
     */
    private function matchesYearly(array $components, CarbonImmutable $moment): bool
    {
        if (isset($components['BYMONTH']) || isset($components['BYWEEKNO']) || isset($components['BYYEARDAY']) || isset($components['BYDAY'])) {
            return true;
        }

        $start = $this->parseDateTime($components['DTSTART'] ?? null, $moment->timezoneName);

        if ($start === null) {
            return false;
        }

        // For yearly frequency, we should match the same month and day, not the day of year
        // This handles leap year differences properly
        return $start->month === $moment->month && $start->day === $moment->day;
    }

    private function isNthWeekdayOfMonth(CarbonImmutable $moment, int $ordinal): bool
    {
        $occurrence = intdiv($moment->day - 1, 7) + 1;

        if ($ordinal > 0) {
            return $occurrence === $ordinal;
        }

        $occurrenceFromEnd = intdiv($moment->daysInMonth - $moment->day, 7) + 1;

        return $occurrenceFromEnd === abs($ordinal);
    }

    private function isNthWeekdayOfYear(CarbonImmutable $moment, int $ordinal): bool
    {
        if ($ordinal > 0) {
            $occurrence = intdiv($moment->dayOfYear - 1, 7) + 1;

            return $occurrence === $ordinal;
        }

        $daysInYear = $moment->isLeapYear() ? 366 : 365;
        $occurrenceFromEnd = intdiv($daysInYear - $moment->dayOfYear, 7) + 1;

        return $occurrenceFromEnd === abs($ordinal);
    }

    /**
     * @return array<int, int>
     */
    private function parseIntList(string $value, int $min, int $max): array
    {
        $numbers = [];

        foreach (explode(',', $value) as $part) {
            $part = trim($part);

            if ($part === '' || ! is_numeric($part)) {
                continue;
            }

            $number = (int) $part;

            if ($number < $min || $number > $max) {
                continue;
            }

            $numbers[$number] = $number;
        }

        return array_values($numbers);
    }

    /**
     * @return array<int, array{weekday: int, ordinal: int|null}>
     */
    private function parseByDayList(string $value): array
    {
        $map = [
            'MO' => 1,
            'TU' => 2,
            'WE' => 3,
            'TH' => 4,
            'FR' => 5,
            'SA' => 6,
            'SU' => 7,
        ];

        $constraints = [];

        foreach (explode(',', $value) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $matches = [];

            if (! preg_match('/^([+-]?\d{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/', $part, $matches)) {
                continue;
            }

            $ordinal = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : null;
            $weekdayCode = $matches[2];

            $constraints[] = [
                'weekday' => $map[$weekdayCode],
                'ordinal' => $ordinal,
            ];
        }

        return $constraints;
    }

    private function parseDateTime(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $formats = [
            'Ymd\THis\Z',
            'Ymd\THis',
            'Ymd',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value, $timezone);
            } catch (Throwable) {
                $date = false;
            }

            if ($date === false) {
                continue;
            }

            if (str_ends_with($format, 'Z')) {
                $date = $date->setTimezone($timezone);
            }

            if (in_array($format, ['Ymd', 'Y-m-d'], true)) {
                $date = $date->startOfDay();
            }

            return $date;
        }

        try {
            return CarbonImmutable::parse($value, $timezone);
        } catch (Throwable) {
            return null;
        }
    }
}
