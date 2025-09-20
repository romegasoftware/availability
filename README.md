# Romega Software - Availability

Reusable availability rule engine for Laravel models. Attach rules to any Eloquent model and evaluate allow/deny windows with a predictable "last matching rule wins" policy.

This package is brought to you by [Romega Software](https://romegasoftware.com). Romega Software is software development agency specializing in helping customers integrate AI and custom software into their business, helping companies in growth mode better acquire, visualize, and utilize their data, and helping entrepreneurs bring their ideas to life.

## Installation

1. Install via Composer:

```bash
composer require romegasoftware/availability
```

2. Publish the config and optional migrations if you need to customise them:

```bash
php artisan vendor:publish --tag=availability-config
php artisan vendor:publish --tag=availability-migrations
```

The service provider auto-registers and the default table name is `availability_rules`.

## Getting Started

1. Add the `HasAvailability` trait (or implement `AvailabilitySubject`) on your model.
2. Create availability rules through the morph relation:

```php
$item->availabilityRules()->create([
    'type' => 'months_of_year',
    'config' => ['months' => [7]],
    'effect' => 'allow',
    'priority' => 10,
]);
```

3. Resolve the engine and evaluate a moment:

```php
$engine = app(\RomegaSoftware\Availability\Support\AvailabilityEngine::class);

if ($engine->isAvailable($item, now())) {
    // accept the booking
}
```

Rules are evaluated in the subject's timezone (`getAvailabilityTimezone`) with a default allow/deny policy provided by `getAvailabilityDefaultEffect`. The final matching rule controls the outcome.

## Rule Types

Configure rule->type to one of the following and store the payload in `config`:

| Type             | Example Config                                                         | Notes                                                                                                            |
| ---------------- | ---------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `months_of_year` | `{"months":[1,7,12]}`                                                  | ISO months (1–12).                                                                                               |
| `weekdays`       | `{"days":[1,2,3,4,5]}`                                                 | ISO weekdays (1=Mon).                                                                                            |
| `date_range`     | `{"from":"05-01","to":"08-31","kind":"yearly"}`                        | Yearly (MM-DD) ranges wrap year-end; absolute expects `Y-m-d`.                                                   |
| `rrule`          | `{"rrule":"FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR","tz":"America/New_York"}` | Supports core `FREQ`, `BYDAY`, `BYMONTH`, `BYMONTHDAY`, `BYHOUR/MINUTE/SECOND`, `INTERVAL` (requires `DTSTART`). |
| `blackout_date`  | `{"dates":["2025-12-25","2025-12-26"]}`                                | Specific calendar dates to deny.                                                                                 |
| `time_of_day`    | `{"from":"09:00","to":"17:00"}`                                        | Inclusive time window. Wraps overnight spans (`22:00` → `04:00`).                                                |
| `inventory_gate` | `{"min":1}`                                                            | Delegates to a resolver that returns numeric stock or boolean availability.                                      |

Combine types for richer policies (e.g. `weekdays` allow + `time_of_day` allow + `blackout_date` deny).

## Inventory Gate Resolver

Provide inventory lookups through config:

```php
config(['availability.inventory_gate' => [
    'resolver' => function (AvailabilitySubject $subject, CarbonInterface $moment, array $ruleConfig): int {
        return $subject->currentStock();
    },
]]);
```

You may also register resolvers per subject class in `availability.inventory_gate.resolvers`. Resolver return values:

- numeric → compared against `config['min']`
- boolean → used directly

Resolvers are cached per subject class, so configure them during boot.

## Custom Evaluators

Register additional evaluators in `config('availability.rule_types')` and implement `RuleEvaluator::matches()`. The registry accepts concrete instances, class names, or container factories.

## Testing

```bash
composer test
```

When shipping new rules, add cases that cover DST boundaries, year transitions, and denial precedence to keep behaviour stable.
