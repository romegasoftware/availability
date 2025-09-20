<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Traits;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RomegaSoftware\Availability\Models\AvailabilityRule;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubject;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubjectWithoutCasts;
use RomegaSoftware\Availability\Tests\TestCase;

final class HasAvailabilityTest extends TestCase
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

    public function test_availability_rules_returns_morph_many_relationship(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $relationship = $subject->availabilityRules();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class, $relationship);
        $this->assertEquals(AvailabilityRule::class, $relationship->getRelated()::class);
        $this->assertEquals('subject_type', $relationship->getMorphType());
        $this->assertEquals('subject_id', $relationship->getForeignKeyName());
    }

    public function test_availability_rules_uses_configured_model(): void
    {
        // Test with default config
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $relationship = $subject->availabilityRules();
        $this->assertEquals(AvailabilityRule::class, $relationship->getRelated()::class);

        // Test with custom model config
        $originalModel = config('availability.models.rule');
        config(['availability.models.rule' => AvailabilityRule::class]);

        $relationship = $subject->availabilityRules();
        $this->assertEquals(AvailabilityRule::class, $relationship->getRelated()::class);

        // Restore original config
        config(['availability.models.rule' => $originalModel]);
    }

    public function test_availability_rules_can_create_and_retrieve_rules(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        // Create rules through the relationship
        $rule1 = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1, 2, 3, 4, 5]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        $rule2 = $subject->availabilityRules()->create([
            'type' => 'time_of_day',
            'config' => ['start' => '09:00', 'end' => '17:00'],
            'effect' => Effect::Deny,
            'priority' => 20,
            'enabled' => true,
        ]);

        // Retrieve rules through the relationship
        $retrievedRules = $subject->availabilityRules()->get();

        $this->assertCount(2, $retrievedRules);
        $this->assertTrue($retrievedRules->contains($rule1));
        $this->assertTrue($retrievedRules->contains($rule2));
    }

    public function test_get_availability_default_effect_when_value_is_effect_instance(): void
    {
        $subject = new TestAvailabilitySubject([
            'availability_default' => Effect::Allow,
        ]);

        $result = $subject->getAvailabilityDefaultEffect();

        $this->assertInstanceOf(Effect::class, $result);
        $this->assertEquals(Effect::Allow, $result);

        // Test with Deny effect
        $subject->availability_default = Effect::Deny;
        $result = $subject->getAvailabilityDefaultEffect();

        $this->assertEquals(Effect::Deny, $result);
    }

    public function test_get_availability_default_effect_when_value_is_valid_string(): void
    {
        $subject = new TestAvailabilitySubject([
            'availability_default' => 'allow',
        ]);

        $result = $subject->getAvailabilityDefaultEffect();

        $this->assertInstanceOf(Effect::class, $result);
        $this->assertEquals(Effect::Allow, $result);

        // Test with 'deny' string
        $subject->availability_default = 'deny';
        $result = $subject->getAvailabilityDefaultEffect();

        $this->assertEquals(Effect::Deny, $result);
    }

    public function test_get_availability_default_effect_when_value_is_empty_string(): void
    {
        $subject = new TestAvailabilitySubjectWithoutCasts([
            'availability_default' => '',
        ]);

        $result = $subject->getAvailabilityDefaultEffect();

        $this->assertInstanceOf(Effect::class, $result);
        $this->assertEquals(Effect::Allow, $result); // Should use config default
    }

    public function test_get_availability_default_effect_when_value_is_null(): void
    {
        $subject = new TestAvailabilitySubject([
            'availability_default' => null,
        ]);

        $result = $subject->getAvailabilityDefaultEffect();

        $this->assertInstanceOf(Effect::class, $result);
        $this->assertEquals(Effect::Allow, $result); // Should use config default
    }

    public function test_get_availability_default_effect_uses_config_default(): void
    {
        // Test with default config value
        $subject = new TestAvailabilitySubject([
            'availability_default' => null,
        ]);

        $result = $subject->getAvailabilityDefaultEffect();
        $this->assertEquals(Effect::Allow, $result);

        // Test with modified config
        $originalDefault = config('availability.default_effect');
        config(['availability.default_effect' => Effect::Deny->value]);

        $result = $subject->getAvailabilityDefaultEffect();
        $this->assertEquals(Effect::Deny, $result);

        // Restore original config
        config(['availability.default_effect' => $originalDefault]);
    }

    public function test_get_availability_default_effect_with_different_config_values(): void
    {
        $originalDefault = config('availability.default_effect');

        // Test with 'allow' config
        config(['availability.default_effect' => 'allow']);
        $subject = new TestAvailabilitySubjectWithoutCasts(['availability_default' => '']);
        $this->assertEquals(Effect::Allow, $subject->getAvailabilityDefaultEffect());

        // Test with 'deny' config
        config(['availability.default_effect' => 'deny']);
        $subject = new TestAvailabilitySubjectWithoutCasts(['availability_default' => '']);
        $this->assertEquals(Effect::Deny, $subject->getAvailabilityDefaultEffect());

        // Restore original config
        config(['availability.default_effect' => $originalDefault]);
    }

    public function test_get_availability_timezone_when_value_is_set(): void
    {
        $subject = new TestAvailabilitySubject([
            'availability_timezone' => 'America/New_York',
        ]);

        $result = $subject->getAvailabilityTimezone();

        $this->assertEquals('America/New_York', $result);
    }

    public function test_get_availability_timezone_when_value_is_empty_string(): void
    {
        $subject = new TestAvailabilitySubject([
            'availability_timezone' => '',
        ]);

        $result = $subject->getAvailabilityTimezone();

        $this->assertNull($result);
    }

    public function test_get_availability_timezone_when_value_is_null(): void
    {
        $subject = new TestAvailabilitySubject([
            'availability_timezone' => null,
        ]);

        $result = $subject->getAvailabilityTimezone();

        $this->assertNull($result);
    }

    public function test_get_availability_timezone_with_various_timezone_values(): void
    {
        $timezones = [
            'UTC',
            'America/New_York',
            'Europe/London',
            'Asia/Tokyo',
            'Australia/Sydney',
            'Pacific/Auckland',
        ];

        foreach ($timezones as $timezone) {
            $subject = new TestAvailabilitySubject([
                'availability_timezone' => $timezone,
            ]);

            $result = $subject->getAvailabilityTimezone();
            $this->assertEquals($timezone, $result, "Failed for timezone: {$timezone}");
        }
    }

    public function test_availability_rules_relationship_with_database_persistence(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        // Create a rule and save to database
        $rule = $subject->availabilityRules()->create([
            'type' => 'weekdays',
            'config' => ['days' => [1, 2, 3, 4, 5]],
            'effect' => Effect::Allow,
            'priority' => 10,
            'enabled' => true,
        ]);

        // Fresh query to ensure it's persisted
        $freshSubject = TestAvailabilitySubject::find($subject->id);
        $rules = $freshSubject->availabilityRules()->get();

        $this->assertCount(1, $rules);
        $this->assertEquals($rule->id, $rules->first()->id);
        $this->assertEquals('weekdays', $rules->first()->type);
        $this->assertEquals(['days' => [1, 2, 3, 4, 5]], $rules->first()->config);
    }

    public function test_availability_rules_relationship_returns_empty_collection_when_no_rules(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);

        $rules = $subject->availabilityRules()->get();

        $this->assertCount(0, $rules);
        $this->assertTrue($rules->isEmpty());
    }

    public function test_multiple_subjects_have_independent_rules(): void
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
            'type' => 'time_of_day',
            'config' => ['start' => '09:00'],
            'effect' => Effect::Deny,
            'priority' => 20,
            'enabled' => true,
        ]);

        // Verify each subject only has its own rules
        $subject1Rules = $subject1->availabilityRules()->get();
        $subject2Rules = $subject2->availabilityRules()->get();

        $this->assertCount(1, $subject1Rules);
        $this->assertCount(1, $subject2Rules);
        $this->assertTrue($subject1Rules->contains($rule1));
        $this->assertFalse($subject1Rules->contains($rule2));
        $this->assertTrue($subject2Rules->contains($rule2));
        $this->assertFalse($subject2Rules->contains($rule1));
    }

    public function test_trait_methods_work_with_persisted_model(): void
    {
        $subject = TestAvailabilitySubject::create([
            'availability_default' => 'deny',
            'availability_timezone' => 'Europe/London',
        ]);

        // Fresh instance from database
        $freshSubject = TestAvailabilitySubject::find($subject->id);

        $this->assertEquals(Effect::Deny, $freshSubject->getAvailabilityDefaultEffect());
        $this->assertEquals('Europe/London', $freshSubject->getAvailabilityTimezone());
    }

    public function test_availability_default_effect_with_model_casts(): void
    {
        // The TestAvailabilitySubject has a cast for availability_default to Effect::class
        $subject = TestAvailabilitySubject::create([
            'availability_default' => 'allow',
        ]);

        // When retrieved from database, it should be cast to Effect enum
        $freshSubject = TestAvailabilitySubject::find($subject->id);

        // The getAttribute call in getAvailabilityDefaultEffect should get the Effect instance
        $result = $freshSubject->getAvailabilityDefaultEffect();
        $this->assertEquals(Effect::Allow, $result);

        // Test with deny
        $subject->update(['availability_default' => 'deny']);
        $freshSubject = TestAvailabilitySubject::find($subject->id);
        $result = $freshSubject->getAvailabilityDefaultEffect();
        $this->assertEquals(Effect::Deny, $result);
    }

    public function test_edge_case_whitespace_only_strings(): void
    {
        // Test availability_default with whitespace - this should fallback to config since it's not a valid Effect
        $subject = new TestAvailabilitySubjectWithoutCasts([
            'availability_default' => '   ',
        ]);

        // Since '   ' is not empty string but also not a valid Effect value,
        // Effect::from() would throw an exception, so this tests error handling
        $this->expectException(\ValueError::class);
        $subject->getAvailabilityDefaultEffect();
    }

    public function test_availability_timezone_with_whitespace(): void
    {
        // Test availability_timezone with whitespace
        $subject = new TestAvailabilitySubject([
            'availability_timezone' => '   ',
        ]);
        $result = $subject->getAvailabilityTimezone();
        $this->assertEquals('   ', $result); // Returns the whitespace string as-is
    }

    public function test_configuration_override_scenarios(): void
    {
        $originalRuleModel = config('availability.models.rule');
        $originalDefaultEffect = config('availability.default_effect');

        try {
            // Test custom rule model configuration
            config(['availability.models.rule' => AvailabilityRule::class]);

            $subject = TestAvailabilitySubject::create([
                'availability_default' => Effect::Allow,
                'availability_timezone' => 'UTC',
            ]);

            $relationship = $subject->availabilityRules();
            $this->assertEquals(AvailabilityRule::class, $relationship->getRelated()::class);

            // Test custom default effect configuration
            config(['availability.default_effect' => 'deny']);

            $subjectWithNullDefault = new TestAvailabilitySubject([
                'availability_default' => null,
            ]);

            $result = $subjectWithNullDefault->getAvailabilityDefaultEffect();
            $this->assertEquals(Effect::Deny, $result);

        } finally {
            // Always restore original configuration
            config(['availability.models.rule' => $originalRuleModel]);
            config(['availability.default_effect' => $originalDefaultEffect]);
        }
    }
}
