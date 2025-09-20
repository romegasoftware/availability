<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Evaluators;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RomegaSoftware\Availability\Evaluators\TimeOfDayEvaluator;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubject;
use RomegaSoftware\Availability\Tests\TestCase;

final class TimeOfDayEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private TimeOfDayEvaluator $evaluator;

    private TestAvailabilitySubject $subject;

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

        $this->evaluator = new TimeOfDayEvaluator;
        $this->subject = TestAvailabilitySubject::create([
            'availability_default' => Effect::Allow,
            'availability_timezone' => 'UTC',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('availability_test_subjects');
        parent::tearDown();
    }

    public function test_normal_time_range(): void
    {
        $config = [
            'from' => '09:00',
            'to' => '17:00',
        ];

        $morning = CarbonImmutable::create(2025, 1, 15, 9, 0, 0, 'UTC');
        $noon = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');
        $evening = CarbonImmutable::create(2025, 1, 15, 17, 0, 0, 'UTC');
        $beforeRange = CarbonImmutable::create(2025, 1, 15, 8, 59, 59, 'UTC');
        $afterRange = CarbonImmutable::create(2025, 1, 15, 17, 0, 1, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $morning, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $noon, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $evening, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $beforeRange, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $afterRange, $this->subject));
    }

    public function test_overnight_time_range(): void
    {
        $config = [
            'from' => '22:00',
            'to' => '06:00',
        ];

        $lateEvening = CarbonImmutable::create(2025, 1, 15, 22, 30, 0, 'UTC');
        $midnight = CarbonImmutable::create(2025, 1, 16, 0, 30, 0, 'UTC');
        $earlyMorning = CarbonImmutable::create(2025, 1, 16, 5, 30, 0, 'UTC');
        $boundary = CarbonImmutable::create(2025, 1, 16, 6, 0, 0, 'UTC');
        $afternoon = CarbonImmutable::create(2025, 1, 15, 14, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $lateEvening, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $midnight, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $earlyMorning, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $boundary, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $afternoon, $this->subject));
    }

    public function test_time_range_with_seconds(): void
    {
        $config = [
            'from' => '09:30:15',
            'to' => '10:45:30',
        ];

        $exactStart = CarbonImmutable::create(2025, 1, 15, 9, 30, 15, 'UTC');
        $withinRange = CarbonImmutable::create(2025, 1, 15, 10, 15, 20, 'UTC');
        $exactEnd = CarbonImmutable::create(2025, 1, 15, 10, 45, 30, 'UTC');
        $beforeStart = CarbonImmutable::create(2025, 1, 15, 9, 30, 14, 'UTC');
        $afterEnd = CarbonImmutable::create(2025, 1, 15, 10, 45, 31, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $exactStart, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $withinRange, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $exactEnd, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $beforeStart, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $afterEnd, $this->subject));
    }

    public function test_same_from_and_to_always_matches(): void
    {
        $config = [
            'from' => '12:00',
            'to' => '12:00',
        ];

        $anyTime1 = CarbonImmutable::create(2025, 1, 15, 0, 0, 0, 'UTC');
        $anyTime2 = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');
        $anyTime3 = CarbonImmutable::create(2025, 1, 15, 23, 59, 59, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $anyTime1, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $anyTime2, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $anyTime3, $this->subject));
    }

    public function test_null_from_returns_false(): void
    {
        $config = [
            'from' => null,
            'to' => '17:00',
        ];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_null_to_returns_false(): void
    {
        $config = [
            'from' => '09:00',
            'to' => null,
        ];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_empty_string_from_returns_false(): void
    {
        $config = [
            'from' => '',
            'to' => '17:00',
        ];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_empty_string_to_returns_false(): void
    {
        $config = [
            'from' => '09:00',
            'to' => '',
        ];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_invalid_time_format_returns_false(): void
    {
        $configs = [
            ['from' => 'invalid', 'to' => '17:00'],
            ['from' => '09:00', 'to' => 'invalid'],
            ['from' => '9:00', 'to' => '17:00'],
            ['from' => '09:00', 'to' => '17:00:00:00'],
            ['from' => '09', 'to' => '17:00'],
            ['from' => '09-00', 'to' => '17:00'],
        ];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        foreach ($configs as $config) {
            $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
        }
    }

    public function test_invalid_hour_returns_false(): void
    {
        $config = [
            'from' => '24:00',
            'to' => '17:00',
        ];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_invalid_minute_returns_false(): void
    {
        $config = [
            'from' => '09:60',
            'to' => '17:00',
        ];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_invalid_second_returns_false(): void
    {
        $config = [
            'from' => '09:30:60',
            'to' => '17:00',
        ];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_missing_config_keys_returns_false(): void
    {
        $config = [];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_midnight_boundary(): void
    {
        $config = [
            'from' => '23:59:59',
            'to' => '00:00:01',
        ];

        $justBeforeMidnight = CarbonImmutable::create(2025, 1, 15, 23, 59, 59, 'UTC');
        $midnight = CarbonImmutable::create(2025, 1, 15, 0, 0, 0, 'UTC');
        $justAfterMidnight = CarbonImmutable::create(2025, 1, 15, 0, 0, 1, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $justBeforeMidnight, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $midnight, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $justAfterMidnight, $this->subject));
    }

    public function test_edge_case_23_59(): void
    {
        $config = [
            'from' => '09:00',
            'to' => '23:59',
        ];

        $endOfDay = CarbonImmutable::create(2025, 1, 15, 23, 59, 0, 'UTC');
        $lastSecond = CarbonImmutable::create(2025, 1, 15, 23, 59, 59, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $endOfDay, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $lastSecond, $this->subject));
    }

    public function test_edge_case_00_00(): void
    {
        $config = [
            'from' => '00:00',
            'to' => '06:00',
        ];

        $startOfDay = CarbonImmutable::create(2025, 1, 15, 0, 0, 0, 'UTC');
        $earlyMorning = CarbonImmutable::create(2025, 1, 15, 3, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $startOfDay, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $earlyMorning, $this->subject));
    }
}
