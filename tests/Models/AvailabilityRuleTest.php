<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Models;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RomegaSoftware\Availability\Models\AvailabilityRule;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubject;
use RomegaSoftware\Availability\Tests\TestCase;

final class AvailabilityRuleTest extends TestCase
{
    use RefreshDatabase;

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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('availability_test_subjects');
        parent::tearDown();
    }

    public function test_get_table_uses_config(): void
    {
        $rule = new AvailabilityRule;

        $this->assertEquals(config('availability.table'), $rule->getTable());
    }

    public function test_get_table_respects_config_changes(): void
    {
        $originalTable = config('availability.table');

        config(['availability.table' => 'custom_availability_rules']);
        $rule = new AvailabilityRule;

        $this->assertEquals('custom_availability_rules', $rule->getTable());

        config(['availability.table' => $originalTable]);
    }

    public function test_subject_relationship(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $rule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1, 2, 3, 4, 5]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $this->assertInstanceOf(TestAvailabilitySubject::class, $rule->subject);
        $this->assertTrue($rule->subject->is($subject));
    }

    public function test_subject_morph_to_relationship(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $rule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1, 2, 3, 4, 5]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $loadedRule = AvailabilityRule::find($rule->id);
        $this->assertInstanceOf(TestAvailabilitySubject::class, $loadedRule->subject);
        $this->assertEquals($subject->id, $loadedRule->subject->id);
    }

    public function test_enabled_scope(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $enabledRule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $disabledRule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [2]],
            'effect' => Effect::Allow,
            'priority' => 20,
            'enabled' => false,
        ]);

        $enabledRules = AvailabilityRule::enabled()->get();

        $this->assertCount(1, $enabledRules);
        $this->assertTrue($enabledRules->first()->is($enabledRule));
        $this->assertFalse($enabledRules->contains($disabledRule));
    }

    public function test_ordered_scope(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $highPriority = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1]],
            'effect' => Effect::Allow,
            'priority' => 100,
            'enabled' => true,
        ]);

        $lowPriority = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [2]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $mediumPriority = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [3]],
            'effect' => Effect::Allow,
            'priority' => 50,
            'enabled' => true,
        ]);

        $orderedRules = AvailabilityRule::ordered()->get();

        $this->assertTrue($orderedRules[0]->is($lowPriority));
        $this->assertTrue($orderedRules[1]->is($mediumPriority));
        $this->assertTrue($orderedRules[2]->is($highPriority));
    }

    public function test_chained_scopes(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $enabledLowPriority = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $disabledLowPriority = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [2]],
            'effect' => Effect::Allow,
            'priority' => 5,
            'enabled' => false,
        ]);

        $enabledHighPriority = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [3]],
            'effect' => Effect::Allow,
            'priority' => 100,
            'enabled' => true,
        ]);

        $results = AvailabilityRule::enabled()->ordered()->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->is($enabledLowPriority));
        $this->assertTrue($results[1]->is($enabledHighPriority));
    }

    public function test_config_cast_to_array(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $config = [
            'days' => [1, 2, 3],
            'nested' => [
                'key' => 'value',
                'another' => ['deep' => 'nesting'],
            ],
        ];

        $rule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => $config,
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $this->assertIsArray($rule->config);
        $this->assertEquals($config, $rule->config);
        $this->assertEquals([1, 2, 3], $rule->config['days']);
        $this->assertEquals('value', $rule->config['nested']['key']);
    }

    public function test_effect_cast_to_enum(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $rule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $this->assertInstanceOf(Effect::class, $rule->effect);
        $this->assertEquals(Effect::Allow, $rule->effect);
        $this->assertEquals('allow', $rule->effect->value);

        $denyRule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [2]],
            'effect' => Effect::Deny,
            'priority' => 20,
            'enabled' => true,
        ]);

        $this->assertEquals(Effect::Deny, $denyRule->effect);
        $this->assertEquals('deny', $denyRule->effect->value);
    }

    public function test_enabled_cast_to_bool(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $rule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => 1,
        ]);

        $this->assertIsBool($rule->enabled);
        $this->assertTrue($rule->enabled);

        $disabledRule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [2]],
            'effect' => Effect::Allow,
            'priority' => 20,
            'enabled' => 0,
        ]);

        $this->assertIsBool($disabledRule->enabled);
        $this->assertFalse($disabledRule->enabled);
    }

    public function test_casts_are_applied_correctly(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        // Test that casts are working by checking the actual casting behavior
        $rule = $subject->availabilityRules()->create([
            'type' => 'test',
            'config' => ['serialized' => 'json'], // Will be stored as JSON and cast back to array
            'effect' => 'allow', // Will be cast to enum
            'priority' => 10,
            'enabled' => 1, // Will be cast to bool
        ]);

        $freshRule = AvailabilityRule::find($rule->id);

        // Verify casts are applied
        $this->assertIsArray($freshRule->config);
        $this->assertEquals(['serialized' => 'json'], $freshRule->config);
        $this->assertInstanceOf(Effect::class, $freshRule->effect);
        $this->assertIsBool($freshRule->enabled);
    }

    public function test_null_config_cast(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $rule = $subject->availabilityRules()->create([
            'type' => 'custom',
            'config' => null,
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $this->assertNull($rule->config);
    }

    public function test_mass_assignment_protection(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $data = [
            'type' => 'weekdays',
            'config' => ['days' => [1, 2, 3]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
            'subject_type' => TestAvailabilitySubject::class,
            'subject_id' => $subject->id,
        ];

        $rule = AvailabilityRule::create($data);

        $this->assertEquals('weekdays', $rule->type);
        $this->assertEquals(['days' => [1, 2, 3]], $rule->config);
        $this->assertEquals(Effect::Allow, $rule->effect);
        $this->assertEquals(10, $rule->priority);
        $this->assertTrue($rule->enabled);
    }

    public function test_priority_with_negative_values(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $negativeRule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1]],
            'effect' => Effect::Allow,
            'priority' => -10,
            'enabled' => true,
        ]);

        $positiveRule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [2]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $orderedRules = AvailabilityRule::ordered()->get();

        $this->assertTrue($orderedRules->first()->is($negativeRule));
        $this->assertEquals(-10, $orderedRules->first()->priority);
    }

    public function test_multiple_subjects_with_rules(): void
    {
        $subject1 = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $subject2 = TestAvailabilitySubject::create([
            'availability_default' => Effect::Deny,
            'availability_timezone' => 'America/New_York',
        ]);

        $rule1 = $subject1->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $rule2 = $subject2->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [2]],
            'effect' => Effect::Deny,
            'priority' => 20,
            'enabled' => true,
        ]);

        $this->assertTrue($rule1->subject->is($subject1));
        $this->assertTrue($rule2->subject->is($subject2));
        $this->assertNotEquals($rule1->subject_id, $rule2->subject_id);
    }

    public function test_json_encoding_of_config(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $complexConfig = [
            'days' => [1, 2, 3],
            'times' => ['09:00', '17:00'],
            'nested' => [
                'deep' => [
                    'value' => 123,
                    'flag' => true,
                ],
            ],
        ];

        $rule = $subject->availabilityRules()->create([
            'type' => 'complex',
            'config' => $complexConfig,
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $json = json_encode($rule);
        $decoded = json_decode($json, true);

        $this->assertEquals($complexConfig, $decoded['config']);
    }

    public function test_update_rule_attributes(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $rule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $rule->update([
            'config' => ['days' => [1, 2, 3, 4, 5]],
            'effect' => Effect::Deny,
            'priority' => 100,
            'enabled' => false,
        ]);

        $this->assertEquals(['days' => [1, 2, 3, 4, 5]], $rule->fresh()->config);
        $this->assertEquals(Effect::Deny, $rule->fresh()->effect);
        $this->assertEquals(100, $rule->fresh()->priority);
        $this->assertFalse($rule->fresh()->enabled);
    }
}
