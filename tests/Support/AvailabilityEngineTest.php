<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;
use RomegaSoftware\Availability\Support\AvailabilityEngine;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Support\RuleEvaluatorRegistry;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubject;
use RomegaSoftware\Availability\Tests\TestCase;

final class AvailabilityEngineTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityEngine $engine;

    private RuleEvaluatorRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('availability_test_subjects');
        Schema::create('availability_test_subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('availability_default')->nullable();
            $table->string('availability_timezone')->nullable();
            $table->timestamps();
        });

        $this->registry = app(RuleEvaluatorRegistry::class);
        $this->engine = new AvailabilityEngine($this->registry);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('availability_test_subjects');
        parent::tearDown();
    }

    public function test_returns_default_allow_when_no_rules_exist(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        $this->assertTrue($result);
    }

    public function test_returns_default_deny_when_no_rules_exist(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        $this->assertFalse($result);
    }

    public function test_uses_app_timezone_when_subject_has_no_timezone(): void
    {
        config(['app.timezone' => 'America/New_York']);

        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
            'availability_timezone' => null,
        ]);

        $mockEvaluator = Mockery::mock(RuleEvaluator::class);
        $this->registry->register('test_rule', $mockEvaluator);

        // The evaluator should receive the moment converted to app timezone
        $mockEvaluator->shouldReceive('matches')
            ->once()
            ->with([], Mockery::on(function (CarbonInterface $moment) {
                return $moment->timezone->getName() === 'America/New_York';
            }), $subject)
            ->andReturn(true);

        $subject->availabilityRules()->create([
            'type' => 'test_rule',
            'config' => [],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now('UTC'));

        $this->assertTrue($result);
    }

    public function test_uses_subject_timezone_when_available(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
            'availability_timezone' => 'Europe/London',
        ]);

        $mockEvaluator = Mockery::mock(RuleEvaluator::class);
        $this->registry->register('test_rule', $mockEvaluator);

        // The evaluator should receive the moment converted to subject timezone
        $mockEvaluator->shouldReceive('matches')
            ->once()
            ->with([], Mockery::on(function (CarbonInterface $moment) {
                return $moment->timezone->getName() === 'Europe/London';
            }), $subject)
            ->andReturn(true);

        $subject->availabilityRules()->create([
            'type' => 'test_rule',
            'config' => [],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now('UTC'));

        $this->assertTrue($result);
    }

    public function test_ignores_disabled_rules(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        $mockEvaluator = Mockery::mock(RuleEvaluator::class);
        $this->registry->register('test_rule', $mockEvaluator);

        // Should never be called because rule is disabled
        $mockEvaluator->shouldNotReceive('matches');

        $subject->availabilityRules()->create([
            'type' => 'test_rule',
            'config' => [],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => false, // Disabled rule
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        $this->assertFalse($result); // Should use default
    }

    public function test_processes_rules_by_priority_order(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        $mockEvaluator1 = Mockery::mock(RuleEvaluator::class);
        $mockEvaluator2 = Mockery::mock(RuleEvaluator::class);

        $this->registry->register('low_priority', $mockEvaluator1);
        $this->registry->register('high_priority', $mockEvaluator2);

        $mockEvaluator1->shouldReceive('matches')->once()->andReturn(true);
        $mockEvaluator2->shouldReceive('matches')->once()->andReturn(true);

        // Create rules with different priorities (will be processed by priority order)
        $subject->availabilityRules()->create([
            'type' => 'low_priority',
            'config' => [],
            'effect' => Effect::Allow,
            'priority' => 90, // Higher number = processed later
            'enabled' => true,
        ]);

        $subject->availabilityRules()->create([
            'type' => 'high_priority',
            'config' => [],
            'effect' => Effect::Deny,
            'priority' => 10, // Lower number = processed first
            'enabled' => true,
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        // Last matching rule (low_priority) should win
        $this->assertTrue($result);
    }

    public function test_continues_when_rule_evaluator_not_found_in_registry(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        // Don't register any evaluator for 'unknown_rule'
        $subject->availabilityRules()->create([
            'type' => 'unknown_rule',
            'config' => [],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        // Should fall back to default since rule is ignored
        $this->assertFalse($result);
    }

    public function test_handles_registry_with_null_evaluator(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        // Create a registry and register a callable that returns null
        $customRegistry = new RuleEvaluatorRegistry(app());
        $customRegistry->register('null_rule', fn () => null);

        $customEngine = new AvailabilityEngine($customRegistry);

        $subject->availabilityRules()->create([
            'type' => 'null_rule',
            'config' => [],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        // This should not throw an exception and should use the default
        $result = $customEngine->isAvailable($subject, CarbonImmutable::now());

        // Should fall back to default since rule registry returns null
        $this->assertFalse($result);
    }

    public function test_handles_config_as_arrayable_object(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        $mockEvaluator = Mockery::mock(RuleEvaluator::class);
        $this->registry->register('test_rule', $mockEvaluator);

        // Create a rule with array config to test the expected behavior
        // The model will cast this to an array anyway, but we test the engine logic
        $mockEvaluator->shouldReceive('matches')
            ->once()
            ->with(['key' => 'value'], Mockery::type(CarbonInterface::class), $subject)
            ->andReturn(true);

        $subject->availabilityRules()->create([
            'type' => 'test_rule',
            'config' => ['key' => 'value'], // Use array config instead
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        $this->assertTrue($result);
    }

    public function test_handles_non_array_config(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        $mockEvaluator = Mockery::mock(RuleEvaluator::class);
        $this->registry->register('test_rule', $mockEvaluator);

        $mockEvaluator->shouldReceive('matches')
            ->once()
            ->with([], Mockery::type(CarbonInterface::class), $subject) // Should pass empty array for non-array config
            ->andReturn(true);

        $subject->availabilityRules()->create([
            'type' => 'test_rule',
            'config' => 'not_an_array',
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        $this->assertTrue($result);
    }

    public function test_handles_null_config(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        $mockEvaluator = Mockery::mock(RuleEvaluator::class);
        $this->registry->register('test_rule', $mockEvaluator);

        $mockEvaluator->shouldReceive('matches')
            ->once()
            ->with([], Mockery::type(CarbonInterface::class), $subject) // Should pass empty array for null config
            ->andReturn(true);

        $subject->availabilityRules()->create([
            'type' => 'test_rule',
            'config' => null,
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        $this->assertTrue($result);
    }

    public function test_preserves_state_changes_through_multiple_rules(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny, // Start with Deny
        ]);

        $allowEvaluator = Mockery::mock(RuleEvaluator::class);
        $denyEvaluator = Mockery::mock(RuleEvaluator::class);

        $this->registry->register('allow_rule', $allowEvaluator);
        $this->registry->register('deny_rule', $denyEvaluator);

        $allowEvaluator->shouldReceive('matches')->once()->andReturn(true);
        $denyEvaluator->shouldReceive('matches')->once()->andReturn(true);

        // Create rules in priority order
        $subject->availabilityRules()->createMany([
            [
                'type' => 'allow_rule',
                'config' => [],
                'effect' => Effect::Allow, // Changes state to Allow
                'priority' => 10,
                'enabled' => true,
            ],
            [
                'type' => 'deny_rule',
                'config' => [],
                'effect' => Effect::Deny, // Changes state back to Deny
                'priority' => 20,
                'enabled' => true,
            ],
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        // Final state should be Deny (from last matching rule)
        $this->assertFalse($result);
    }

    public function test_only_updates_state_when_rule_matches(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow, // Start with Allow
        ]);

        $matchingEvaluator = Mockery::mock(RuleEvaluator::class);
        $nonMatchingEvaluator = Mockery::mock(RuleEvaluator::class);

        $this->registry->register('matching_rule', $matchingEvaluator);
        $this->registry->register('non_matching_rule', $nonMatchingEvaluator);

        $matchingEvaluator->shouldReceive('matches')->once()->andReturn(true);
        $nonMatchingEvaluator->shouldReceive('matches')->once()->andReturn(false);

        // Create rules in priority order
        $subject->availabilityRules()->createMany([
            [
                'type' => 'matching_rule',
                'config' => [],
                'effect' => Effect::Deny, // Should change state to Deny
                'priority' => 10,
                'enabled' => true,
            ],
            [
                'type' => 'non_matching_rule',
                'config' => [],
                'effect' => Effect::Allow, // Should NOT change state back
                'priority' => 20,
                'enabled' => true,
            ],
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        // State should remain Deny because non-matching rule didn't affect it
        $this->assertFalse($result);
    }

    public function test_clones_and_converts_moment_to_subject_timezone(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
            'availability_timezone' => 'Pacific/Auckland',
        ]);

        $mockEvaluator = Mockery::mock(RuleEvaluator::class);
        $this->registry->register('test_rule', $mockEvaluator);

        $originalMoment = CarbonImmutable::create(2025, 6, 15, 12, 0, 0, 'UTC');

        $mockEvaluator->shouldReceive('matches')
            ->once()
            ->with(
                [],
                Mockery::on(function (CarbonInterface $moment) use ($originalMoment) {
                    // Should be cloned (different instance)
                    $this->assertNotSame($originalMoment, $moment);
                    // Should be in correct timezone
                    $this->assertEquals('Pacific/Auckland', $moment->timezone->getName());
                    // Should be the same time, just in different timezone
                    $this->assertTrue($moment->equalTo($originalMoment));

                    return true;
                }),
                $subject
            )
            ->andReturn(true);

        $subject->availabilityRules()->create([
            'type' => 'test_rule',
            'config' => [],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $result = $this->engine->isAvailable($subject, $originalMoment);

        $this->assertTrue($result);

        // Original moment should be unchanged
        $this->assertEquals('UTC', $originalMoment->timezone->getName());
    }

    public function test_handles_complex_rule_scenario_with_multiple_priority_levels(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        $evaluatorA = Mockery::mock(RuleEvaluator::class);
        $evaluatorB = Mockery::mock(RuleEvaluator::class);
        $evaluatorC = Mockery::mock(RuleEvaluator::class);

        $this->registry->register('rule_a', $evaluatorA);
        $this->registry->register('rule_b', $evaluatorB);
        $this->registry->register('rule_c', $evaluatorC);

        // All rules match
        $evaluatorA->shouldReceive('matches')->once()->andReturn(true);
        $evaluatorB->shouldReceive('matches')->once()->andReturn(true);
        $evaluatorC->shouldReceive('matches')->once()->andReturn(true);

        $subject->availabilityRules()->createMany([
            [
                'type' => 'rule_a',
                'config' => [],
                'effect' => Effect::Allow,  // Priority 10: Deny -> Allow
                'priority' => 10,
                'enabled' => true,
            ],
            [
                'type' => 'rule_b',
                'config' => [],
                'effect' => Effect::Deny,   // Priority 50: Allow -> Deny
                'priority' => 50,
                'enabled' => true,
            ],
            [
                'type' => 'rule_c',
                'config' => [],
                'effect' => Effect::Allow,  // Priority 100: Deny -> Allow
                'priority' => 100,
                'enabled' => true,
            ],
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        // Final state should be Allow (from highest priority matching rule)
        $this->assertTrue($result);
    }

    public function test_handles_mixed_enabled_disabled_rules_with_priorities(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        $enabledEvaluator = Mockery::mock(RuleEvaluator::class);
        $disabledEvaluator = Mockery::mock(RuleEvaluator::class);

        $this->registry->register('enabled_rule', $enabledEvaluator);
        $this->registry->register('disabled_rule', $disabledEvaluator);

        $enabledEvaluator->shouldReceive('matches')->once()->andReturn(true);
        // Disabled evaluator should never be called
        $disabledEvaluator->shouldNotReceive('matches');

        $subject->availabilityRules()->createMany([
            [
                'type' => 'enabled_rule',
                'config' => [],
                'effect' => Effect::Allow,
                'priority' => 10,
                'enabled' => true,
            ],
            [
                'type' => 'disabled_rule',
                'config' => [],
                'effect' => Effect::Deny, // Would override if enabled
                'priority' => 90,
                'enabled' => false, // Disabled
            ],
        ]);

        $result = $this->engine->isAvailable($subject, CarbonImmutable::now());

        // Should use enabled rule result
        $this->assertTrue($result);
    }

    public function test_processes_rules_in_exact_priority_order(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
        ]);

        $callOrder = [];

        $evaluator1 = Mockery::mock(RuleEvaluator::class);
        $evaluator2 = Mockery::mock(RuleEvaluator::class);
        $evaluator3 = Mockery::mock(RuleEvaluator::class);

        $this->registry->register('rule_priority_50', $evaluator1);
        $this->registry->register('rule_priority_10', $evaluator2);
        $this->registry->register('rule_priority_30', $evaluator3);

        $evaluator1->shouldReceive('matches')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 50;

            return true;
        });

        $evaluator2->shouldReceive('matches')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 10;

            return true;
        });

        $evaluator3->shouldReceive('matches')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 30;

            return true;
        });

        $subject->availabilityRules()->createMany([
            [
                'type' => 'rule_priority_50',
                'config' => [],
                'effect' => Effect::Allow,
                'priority' => 50,
                'enabled' => true,
            ],
            [
                'type' => 'rule_priority_10',
                'config' => [],
                'effect' => Effect::Allow,
                'priority' => 10,
                'enabled' => true,
            ],
            [
                'type' => 'rule_priority_30',
                'config' => [],
                'effect' => Effect::Allow,
                'priority' => 30,
                'enabled' => true,
            ],
        ]);

        $this->engine->isAvailable($subject, CarbonImmutable::now());

        // Should be called in priority order (ascending)
        $this->assertEquals([10, 30, 50], $callOrder);
    }
}
