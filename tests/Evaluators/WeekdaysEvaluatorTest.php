<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Evaluators;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RomegaSoftware\Availability\Evaluators\WeekdaysEvaluator;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubject;
use RomegaSoftware\Availability\Tests\TestCase;

final class WeekdaysEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private WeekdaysEvaluator $evaluator;

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

        $this->evaluator = new WeekdaysEvaluator;
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

    public function test_single_weekday(): void
    {
        $config = ['days' => [1]];

        $monday = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');
        $tuesday = CarbonImmutable::create(2025, 1, 7, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $monday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $tuesday, $this->subject));
    }

    public function test_multiple_weekdays(): void
    {
        $config = ['days' => [1, 3, 5]];

        $monday = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');
        $tuesday = CarbonImmutable::create(2025, 1, 7, 12, 0, 0, 'UTC');
        $wednesday = CarbonImmutable::create(2025, 1, 8, 12, 0, 0, 'UTC');
        $thursday = CarbonImmutable::create(2025, 1, 9, 12, 0, 0, 'UTC');
        $friday = CarbonImmutable::create(2025, 1, 10, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $monday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $tuesday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $wednesday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $thursday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $friday, $this->subject));
    }

    public function test_weekdays_only(): void
    {
        $config = ['days' => [1, 2, 3, 4, 5]];

        $monday = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');
        $tuesday = CarbonImmutable::create(2025, 1, 7, 12, 0, 0, 'UTC');
        $wednesday = CarbonImmutable::create(2025, 1, 8, 12, 0, 0, 'UTC');
        $thursday = CarbonImmutable::create(2025, 1, 9, 12, 0, 0, 'UTC');
        $friday = CarbonImmutable::create(2025, 1, 10, 12, 0, 0, 'UTC');
        $saturday = CarbonImmutable::create(2025, 1, 11, 12, 0, 0, 'UTC');
        $sunday = CarbonImmutable::create(2025, 1, 12, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $monday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $tuesday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $wednesday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $thursday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $friday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $saturday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $sunday, $this->subject));
    }

    public function test_weekends_only(): void
    {
        $config = ['days' => [6, 7]];

        $friday = CarbonImmutable::create(2025, 1, 10, 12, 0, 0, 'UTC');
        $saturday = CarbonImmutable::create(2025, 1, 11, 12, 0, 0, 'UTC');
        $sunday = CarbonImmutable::create(2025, 1, 12, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $friday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $saturday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $sunday, $this->subject));
    }

    public function test_all_days_of_week(): void
    {
        $config = ['days' => [1, 2, 3, 4, 5, 6, 7]];

        for ($day = 6; $day <= 12; $day++) {
            $moment = CarbonImmutable::create(2025, 1, $day, 12, 0, 0, 'UTC');
            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        }
    }

    public function test_empty_days_array_returns_false(): void
    {
        $config = ['days' => []];

        $moment = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_missing_days_key_returns_false(): void
    {
        $config = [];

        $moment = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_non_numeric_days_are_filtered(): void
    {
        $config = ['days' => ['monday', null, 1, 'one', [], new \stdClass]];

        $monday = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $monday, $this->subject));
    }

    public function test_string_numeric_days_are_accepted(): void
    {
        $config = ['days' => ['1', '5']];

        $monday = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');
        $friday = CarbonImmutable::create(2025, 1, 10, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $monday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $friday, $this->subject));
    }

    public function test_out_of_range_days_are_filtered(): void
    {
        $config = ['days' => [0, 1, 8, 100, -1]];

        $monday = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');
        $tuesday = CarbonImmutable::create(2025, 1, 7, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $monday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $tuesday, $this->subject));
    }

    public function test_only_out_of_range_days_returns_false(): void
    {
        $config = ['days' => [0, 8, 100, -1]];

        $monday = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $monday, $this->subject));
    }

    public function test_duplicate_days_are_deduplicated(): void
    {
        $config = ['days' => [1, 1, 1, 1]];

        $monday = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $monday, $this->subject));
    }

    public function test_time_of_day_doesnt_affect_weekday(): void
    {
        $config = ['days' => [1]];

        $mondayMorning = CarbonImmutable::create(2025, 1, 6, 0, 0, 0, 'UTC');
        $mondayNoon = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');
        $mondayNight = CarbonImmutable::create(2025, 1, 6, 23, 59, 59, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $mondayMorning, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $mondayNoon, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $mondayNight, $this->subject));
    }

    public function test_timezone_handling(): void
    {
        $config = ['days' => [1]];

        $utcMonday = CarbonImmutable::create(2025, 1, 6, 23, 0, 0, 'UTC');
        $nyMonday = CarbonImmutable::create(2025, 1, 6, 18, 0, 0, 'America/New_York');

        $this->assertTrue($this->evaluator->matches($config, $utcMonday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $nyMonday, $this->subject));
    }

    public function test_float_days_are_accepted(): void
    {
        $config = ['days' => [1.5, 1.9]];

        $monday = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $monday, $this->subject));
    }

    public function test_only_non_numeric_values_returns_false(): void
    {
        $config = ['days' => ['monday', 'tuesday', 'wednesday']];

        $moment = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_iso_weekday_numbering(): void
    {
        $config = ['days' => [7]];

        $sunday = CarbonImmutable::create(2025, 1, 12, 12, 0, 0, 'UTC');
        $monday = CarbonImmutable::create(2025, 1, 6, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $sunday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $monday, $this->subject));
    }
}
