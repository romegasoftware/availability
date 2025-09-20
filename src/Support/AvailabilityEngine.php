<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;

final class AvailabilityEngine
{
    public function __construct(private RuleEvaluatorRegistry $registry) {}

    public function isAvailable(AvailabilitySubject $subject, CarbonInterface $at): bool
    {
        $timezone = $subject->getAvailabilityTimezone() ?? config('app.timezone');
        $localizedMoment = $at->clone()->setTimezone($timezone);

        $rules = $subject->availabilityRules()
            ->where('enabled', true)
            ->orderBy('priority')
            ->get();

        $state = $subject->getAvailabilityDefaultEffect()->allows();

        foreach ($rules as $rule) {
            $evaluator = $this->registry->get($rule->type);

            if (! $evaluator instanceof RuleEvaluator) {
                continue;
            }

            $config = $rule->config;

            if ($config instanceof Arrayable) {
                $config = $config->toArray();
            }

            if ($evaluator->matches(is_array($config) ? $config : [], $localizedMoment, $subject)) {
                $state = $rule->effect->allows();
            }
        }

        return $state;
    }
}
