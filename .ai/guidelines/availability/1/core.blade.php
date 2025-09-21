@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
$roster = app(\Laravel\Roster\Roster::class);
$availabilityPackage = $roster->packages()->firstWhere(fn ($package) => $package->rawName() === 'romegasoftware/availability');
@endphp
## Romega Availability
@if ($availabilityPackage)
- Note the installed version: v{{ $availabilityPackage->version() }} (major {{ $availabilityPackage->majorVersion() }}). Align any examples with this release.
@else
- Note the installed version of Romega Software Availability package: v1.0. Align any examples with this release.
@endif
- Treat availability as a last-matching-rule system: lower priorities run first, higher priorities win.
- Set an explicit default effect on each subject so fallback behaviour is intentional.
- Keep rule configs concise and self-documenting by adding `_description` fields when the team relies on UI-driven rule management.

## Model Setup
- Add the `HasAvailability` trait to every subject that needs scheduling or implement `AvailabilitySubject` when you require a custom relationship.
- Cast `availability_default` to the `Effect` enum so reads and writes stay type-safe.
- Override `getAvailabilityTimezone()` when the timezone depends on related models (location, owner, tenant).
- Expose helper methods (e.g. `setBusinessHours`) that group rule creation instead of duplicating rule arrays across services.

@boostsnippet("Model With Availability", "php")
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Traits\HasAvailability;

class Room extends Model
{
    use HasAvailability;

    protected $fillable = ['name', 'availability_default', 'availability_timezone'];

    protected $casts = ['availability_default' => Effect::class];

    public function bootDefaultAvailability(): void
    {
        if ($this->wasRecentlyCreated) {
            $this->setBusinessHours('09:00', '17:00');
        }
    }

    public function setBusinessHours(string $from, string $to): void
    {
        $this->availabilityRules()->createMany([
            [
                'type' => 'weekdays',
                'config' => ['days' => [1, 2, 3, 4, 5], '_description' => 'Weekday schedule'],
                'effect' => Effect::Allow,
                'priority' => 10,
            ],
            [
                'type' => 'time_of_day',
                'config' => ['from' => $from, 'to' => $to],
                'effect' => Effect::Allow,
                'priority' => 20,
            ],
        ]);
    }
}
@endboostsnippet

## Rule Authoring
### Priority Strategy
- Define priority constants (e.g. base 10/20/30/40) so overrides have predictable gaps.
- Place emergency blackouts at the highest priority so they always win.
- Leave unused space between priority bands to simplify later insertions.
- Audit rule order by sorting enabled rules and tracing their effects whenever availability looks wrong.

### Rule Lifecycle
- Prefer toggling `enabled` over deleting rules so you can quickly restore prior behaviour.
- Store human-readable names in rule config (e.g. `_label` or `_description`) to explain intent in UIs and logs.
- Wrap related rules in dedicated service methods to avoid copy/paste arrays throughout the codebase.
- Use morph relationship scopes (e.g. `scopeActive`) to hide expired or disabled rules from everyday reads.

### Rule Types
- Use `weekdays`, `months_of_year`, and `date_range` to anchor schedules to calendar patterns; provide ISO day/month numbers.
- Configure `time_of_day` rules with inclusive `from`/`to` values; remember midnight wraparound is supported when `from` > `to`.
- Apply `blackout_date` for specific dates that must always deny availability regardless of other rules.
- Reach for `rrule` when you need RFC 5545 recurrences (first Monday, every 2 weeks, etc.).
- Gate capacity with `inventory_gate` only after registering appropriate resolvers.

@boostsnippet("Baseline Schedule", "php")
use App\Models\Room;
use RomegaSoftware\Availability\Support\Effect;

final class RulePriorities
{
    public const BASE = 10;
    public const SCHEDULE = 20;
    public const BLACKOUT = 80;
}

$room = Room::findOrFail($id);

$room->availability_default = Effect::Deny;
$room->availability_timezone = 'America/New_York';
$room->save();

$room->availabilityRules()->createMany([
    [
        'type' => 'weekdays',
        'config' => ['days' => [1, 2, 3, 4, 5]],
        'effect' => Effect::Allow,
        'priority' => RulePriorities::BASE,
    ],
    [
        'type' => 'time_of_day',
        'config' => ['from' => '09:00', 'to' => '17:00'],
        'effect' => Effect::Allow,
        'priority' => RulePriorities::SCHEDULE,
    ],
    [
        'type' => 'blackout_date',
        'config' => ['dates' => ['2025-12-25'], '_description' => 'Christmas closure'],
        'effect' => Effect::Deny,
        'priority' => RulePriorities::BLACKOUT,
    ],
]);
@endboostsnippet

## Availability Engine Usage
- Resolve `AvailabilityEngine` from the container to evaluate rules instead of manually iterating configurations.
- Pass `Carbon` instances in the callers timezone; the engine converts to the subject timezone automatically.
- Batch availability checks by rounding timestamps or iterating at coarse intervals to limit repeated queries.
- Surface helper methods (e.g. `findNextAvailable`) that step through future moments and short-circuit once availability flips.

## Inventory Gates
- Register a global resolver in `config/availability.php` or a service provider when one rule fits all subjects.
- Provide per-model resolvers via `availability.inventory_gate.resolvers` when stock, bookings, or capacity live on different models.
- Return numeric counts when you rely on `min` thresholds; return booleans when a simple pass/fail gate is sufficient.
- Cache expensive resolver work (API calls, aggregations) with TTLs and flush caches whenever rules or inventory change.

@boostsnippet("Inventory Resolver", "php")
use App\Models\Product;
use Carbon\CarbonInterface;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;

config(['availability.inventory_gate.resolvers' => [
    Product::class => function (AvailabilitySubject $subject, CarbonInterface $moment, array $ruleConfig) {
        $warehouse = $ruleConfig['warehouse_id'] ?? null;

        $stock = $subject->inventory()
            ->when($warehouse, fn ($query) => $query->where('warehouse_id', $warehouse))
            ->sum('quantity');

        $reserved = $subject->orderItems()
            ->whereIn('status', ['pending', 'processing'])
            ->sum('quantity');

        return max(0, $stock - $reserved);
    },
],]);
@endboostsnippet

## Custom Evaluators
- Implement `RuleEvaluator::matches` for any domain rule that the built-ins cannot express (lead time, dependencies, quotas).
- Validate required config keys up front and fall back to a safe default (`false`) when the payload is malformed.
- Register evaluators either in `config/availability.php` or dynamically through `RuleEvaluatorRegistry` inside a service provider.
- Keep evaluators focused on one responsibility and delegate expensive logic to collaborators so they stay testable.

## Testing
- Seed rules in factories or helper methods so feature tests can assume consistent schedules.
- Configure inventory resolvers inside tests (`config([...])`) to avoid hitting live services.
- Assert both available and unavailable states around priority edges to prove overrides behave correctly.
- Cover edge cases such as DST transitions, leap days, and overlapping rules with dedicated tests.

## Performance & Diagnostics
- Eager load `availabilityRules` when checking many subjects to avoid N+1 queries.
- Select only needed columns (`type`, `config`, `effect`, `priority`) when building evaluation lists.
- Cache frequently-checked results with short TTLs and flush caches when rules mutate.
- Log rule evaluation order while debugging by iterating enabled rules sorted by priority and capturing their effects.
- Instrument long-running checks with timing metrics so you can spot slow resolvers or excessive rule counts early.
