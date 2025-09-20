<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Evaluators;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RomegaSoftware\Availability\Evaluators\MonthsOfYearEvaluator;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubject;
use RomegaSoftware\Availability\Tests\TestCase;

final class MonthsOfYearEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private MonthsOfYearEvaluator $evaluator;

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

        $this->evaluator = new MonthsOfYearEvaluator;
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

    public function test_single_month(): void
    {
        $config = ['months' => [7]];

        $inJuly = CarbonImmutable::create(2025, 7, 15, 12, 0, 0, 'UTC');
        $inAugust = CarbonImmutable::create(2025, 8, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $inJuly, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $inAugust, $this->subject));
    }

    public function test_multiple_months(): void
    {
        $config = ['months' => [1, 6, 7, 12]];

        $inJanuary = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');
        $inFebruary = CarbonImmutable::create(2025, 2, 15, 12, 0, 0, 'UTC');
        $inJune = CarbonImmutable::create(2025, 6, 15, 12, 0, 0, 'UTC');
        $inJuly = CarbonImmutable::create(2025, 7, 15, 12, 0, 0, 'UTC');
        $inDecember = CarbonImmutable::create(2025, 12, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $inJanuary, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $inFebruary, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $inJune, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $inJuly, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $inDecember, $this->subject));
    }

    public function test_empty_months_array_returns_false(): void
    {
        $config = ['months' => []];

        $moment = CarbonImmutable::create(2025, 7, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_missing_months_key_returns_false(): void
    {
        $config = [];

        $moment = CarbonImmutable::create(2025, 7, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_non_numeric_months_are_filtered(): void
    {
        $config = ['months' => ['july', null, 7, 'seven', [], new \stdClass]];

        $inJuly = CarbonImmutable::create(2025, 7, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $inJuly, $this->subject));
    }

    public function test_string_numeric_months_are_accepted(): void
    {
        $config = ['months' => ['7', '12']];

        $inJuly = CarbonImmutable::create(2025, 7, 15, 12, 0, 0, 'UTC');
        $inDecember = CarbonImmutable::create(2025, 12, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $inJuly, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $inDecember, $this->subject));
    }

    public function test_out_of_range_months_dont_match(): void
    {
        $config = ['months' => [0, 13, 100, -1]];

        $inJuly = CarbonImmutable::create(2025, 7, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $inJuly, $this->subject));
    }

    public function test_all_months(): void
    {
        $config = ['months' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]];

        for ($month = 1; $month <= 12; $month++) {
            $moment = CarbonImmutable::create(2025, $month, 15, 12, 0, 0, 'UTC');
            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        }
    }

    public function test_first_and_last_day_of_month(): void
    {
        $config = ['months' => [7]];

        $firstDay = CarbonImmutable::create(2025, 7, 1, 0, 0, 0, 'UTC');
        $lastDay = CarbonImmutable::create(2025, 7, 31, 23, 59, 59, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $firstDay, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $lastDay, $this->subject));
    }

    public function test_timezone_doesnt_affect_month(): void
    {
        $config = ['months' => [7]];

        $utcTime = CarbonImmutable::create(2025, 7, 15, 23, 0, 0, 'UTC');
        $nyTime = CarbonImmutable::create(2025, 7, 15, 19, 0, 0, 'America/New_York');
        $tokyoTime = CarbonImmutable::create(2025, 7, 16, 12, 0, 0, 'Asia/Tokyo');

        $this->assertTrue($this->evaluator->matches($config, $utcTime, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $nyTime, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $tokyoTime, $this->subject));
    }

    public function test_only_non_numeric_values_returns_false(): void
    {
        $config = ['months' => ['january', 'february', 'march']];

        $moment = CarbonImmutable::create(2025, 1, 15, 12, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_float_months_are_accepted(): void
    {
        $config = ['months' => [7.5, 7.9]];

        $inJuly = CarbonImmutable::create(2025, 7, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $inJuly, $this->subject));
    }

    public function test_leap_year_february(): void
    {
        $config = ['months' => [2]];

        $leapYearFeb = CarbonImmutable::create(2024, 2, 29, 12, 0, 0, 'UTC');
        $normalYearFeb = CarbonImmutable::create(2025, 2, 28, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $leapYearFeb, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $normalYearFeb, $this->subject));
    }

    public function test_duplicate_months_are_handled(): void
    {
        $config = ['months' => [7, 7, 7, 7]];

        $inJuly = CarbonImmutable::create(2025, 7, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $inJuly, $this->subject));
    }
}
