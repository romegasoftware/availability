<?php

declare(strict_types=1);

use RomegaSoftware\Availability\Evaluators\BlackoutDateEvaluator;
use RomegaSoftware\Availability\Evaluators\DateRangeEvaluator;
use RomegaSoftware\Availability\Evaluators\InventoryGateEvaluator;
use RomegaSoftware\Availability\Evaluators\MonthsOfYearEvaluator;
use RomegaSoftware\Availability\Evaluators\RRuleEvaluator;
use RomegaSoftware\Availability\Evaluators\TimeOfDayEvaluator;
use RomegaSoftware\Availability\Evaluators\WeekdaysEvaluator;
use RomegaSoftware\Availability\Models\AvailabilityRule;
use RomegaSoftware\Availability\Support\Effect;

return [
    'table' => 'availability_rules',
    'default_effect' => Effect::Allow->value,
    'models' => [
        'rule' => AvailabilityRule::class,
    ],
    'rule_types' => [
        'months_of_year' => MonthsOfYearEvaluator::class,
        'weekdays' => WeekdaysEvaluator::class,
        'date_range' => DateRangeEvaluator::class,
        'rrule' => RRuleEvaluator::class,
        'blackout_date' => BlackoutDateEvaluator::class,
        'time_of_day' => TimeOfDayEvaluator::class,
        'inventory_gate' => InventoryGateEvaluator::class,
    ],
    'inventory_gate' => [
        'resolver' => null,
        'resolvers' => [],
    ],
];
