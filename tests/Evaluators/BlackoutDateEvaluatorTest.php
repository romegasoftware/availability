<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Evaluators;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RomegaSoftware\Availability\Evaluators\BlackoutDateEvaluator;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubject;
use RomegaSoftware\Availability\Tests\TestCase;

final class BlackoutDateEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private BlackoutDateEvaluator $evaluator;

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

        $this->evaluator = new BlackoutDateEvaluator;
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

    public function test_single_blackout_date(): void
    {
        $config = ['dates' => ['2025-01-15']];

        $onBlackout = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');
        $beforeBlackout = CarbonImmutable::create(2025, 1, 14, 12, 0, 0, 'UTC');
        $afterBlackout = CarbonImmutable::create(2025, 1, 16, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $onBlackout, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $beforeBlackout, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $afterBlackout, $this->subject));
    }

    public function test_multiple_blackout_dates(): void
    {
        $config = ['dates' => ['2025-01-15', '2025-01-20', '2025-02-14']];

        $onFirst = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');
        $onSecond = CarbonImmutable::create(2025, 1, 20, 12, 0, 0, 'UTC');
        $onThird = CarbonImmutable::create(2025, 2, 14, 12, 0, 0, 'UTC');
        $notBlackout = CarbonImmutable::create(2025, 1, 17, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $onFirst, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $onSecond, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $onThird, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $notBlackout, $this->subject));
    }

    public function test_empty_dates_array_returns_false(): void
    {
        $config = ['dates' => []];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_missing_dates_key_returns_false(): void
    {
        $config = [];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_invalid_date_format_is_filtered(): void
    {
        $config = ['dates' => ['invalid-date', '2025-01-15', '01-15-2025', '2025/01/15']];

        $onValidDate = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $onValidDate, $this->subject));
    }

    public function test_non_string_dates_are_filtered(): void
    {
        $config = ['dates' => [null, 123, ['2025-01-15'], new \stdClass, '2025-01-15']];

        $onValidDate = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $onValidDate, $this->subject));
    }

    public function test_empty_string_dates_are_filtered(): void
    {
        $config = ['dates' => ['', '2025-01-15', '  ']];

        $onValidDate = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $onValidDate, $this->subject));
    }

    public function test_duplicate_dates_are_deduplicated(): void
    {
        $config = ['dates' => ['2025-01-15', '2025-01-15', '2025-01-15']];

        $onDate = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $onDate, $this->subject));
    }

    public function test_time_of_day_is_ignored(): void
    {
        $config = ['dates' => ['2025-01-15']];

        $earlyMorning = CarbonImmutable::create(2025, 1, 15, 0, 0, 0, 'UTC');
        $noon = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');
        $lateNight = CarbonImmutable::create(2025, 1, 15, 23, 59, 59, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $earlyMorning, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $noon, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $lateNight, $this->subject));
    }

    public function test_timezone_handling(): void
    {
        $config = ['dates' => ['2025-01-15']];

        $utcTime = CarbonImmutable::create(2025, 1, 15, 23, 0, 0, 'UTC');
        $nyTime = CarbonImmutable::create(2025, 1, 15, 18, 0, 0, 'America/New_York');

        $this->assertTrue($this->evaluator->matches($config, $utcTime, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $nyTime, $this->subject));
    }

    public function test_only_invalid_dates_returns_false(): void
    {
        $config = ['dates' => ['invalid1', 'invalid2', 'not-a-date']];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_future_dates(): void
    {
        $config = ['dates' => ['2030-12-31']];

        $futureDate = CarbonImmutable::create(2030, 12, 31, 12, 0, 0, 'UTC');
        $currentDate = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $futureDate, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $currentDate, $this->subject));
    }

    public function test_past_dates(): void
    {
        $config = ['dates' => ['2020-01-01']];

        $pastDate = CarbonImmutable::create(2020, 1, 1, 12, 0, 0, 'UTC');
        $currentDate = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $pastDate, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $currentDate, $this->subject));
    }

    public function test_leap_year_february_29(): void
    {
        $config = ['dates' => ['2024-02-29']];

        $leapDay = CarbonImmutable::create(2024, 2, 29, 12, 0, 0, 'UTC');
        $regularDay = CarbonImmutable::create(2024, 2, 28, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $leapDay, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $regularDay, $this->subject));
    }

    public function test_invalid_leap_year_date_is_filtered(): void
    {
        $config = ['dates' => ['2025-02-29', '2025-02-28']];

        $validDate = CarbonImmutable::create(2025, 2, 28, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $validDate, $this->subject));
    }

    public function test_dates_at_year_boundaries(): void
    {
        $config = ['dates' => ['2025-01-01', '2025-12-31']];

        $newYearsDay = CarbonImmutable::create(2025, 1, 1, 0, 0, 0, 'UTC');
        $newYearsEve = CarbonImmutable::create(2025, 12, 31, 23, 59, 59, 'UTC');
        $regularDay = CarbonImmutable::create(2025, 6, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $newYearsDay, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $newYearsEve, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $regularDay, $this->subject));
    }
}
