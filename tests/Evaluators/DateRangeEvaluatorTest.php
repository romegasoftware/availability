<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Evaluators;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RomegaSoftware\Availability\Evaluators\DateRangeEvaluator;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubject;
use RomegaSoftware\Availability\Tests\TestCase;

final class DateRangeEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private DateRangeEvaluator $evaluator;

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

        $this->evaluator = new DateRangeEvaluator;
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

    public function test_yearly_range_within_same_year(): void
    {
        $config = [
            'kind' => 'yearly',
            'from' => '03-15',
            'to' => '07-20',
        ];

        $insideRange = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');
        $beforeRange = CarbonImmutable::create(2025, 3, 14, 10, 0, 0, 'UTC');
        $afterRange = CarbonImmutable::create(2025, 7, 21, 10, 0, 0, 'UTC');
        $onStartBoundary = CarbonImmutable::create(2025, 3, 15, 10, 0, 0, 'UTC');
        $onEndBoundary = CarbonImmutable::create(2025, 7, 20, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $insideRange, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $beforeRange, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $afterRange, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $onStartBoundary, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $onEndBoundary, $this->subject));
    }

    public function test_yearly_range_wrapping_year_boundary(): void
    {
        $config = [
            'kind' => 'yearly',
            'from' => '11-01',
            'to' => '02-28',
        ];

        $inDecember = CarbonImmutable::create(2024, 12, 15, 10, 0, 0, 'UTC');
        $inJanuary = CarbonImmutable::create(2025, 1, 15, 10, 0, 0, 'UTC');
        $inFebruary = CarbonImmutable::create(2025, 2, 15, 10, 0, 0, 'UTC');
        $inMarch = CarbonImmutable::create(2025, 3, 1, 10, 0, 0, 'UTC');
        $inJuly = CarbonImmutable::create(2025, 7, 15, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $inDecember, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $inJanuary, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $inFebruary, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $inMarch, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $inJuly, $this->subject));
    }

    public function test_yearly_range_with_null_from(): void
    {
        $config = [
            'kind' => 'yearly',
            'from' => null,
            'to' => '07-20',
        ];

        $moment = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_yearly_range_with_null_to(): void
    {
        $config = [
            'kind' => 'yearly',
            'from' => '03-15',
            'to' => null,
        ];

        $moment = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_yearly_range_with_empty_string_boundaries(): void
    {
        $config = [
            'kind' => 'yearly',
            'from' => '',
            'to' => '',
        ];

        $moment = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_yearly_range_with_invalid_date_format(): void
    {
        $config = [
            'kind' => 'yearly',
            'from' => 'invalid',
            'to' => '07-20',
        ];

        $moment = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_absolute_range_normal_case(): void
    {
        $config = [
            'kind' => 'absolute',
            'from' => '2025-03-15',
            'to' => '2025-07-20',
        ];

        $insideRange = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');
        $beforeRange = CarbonImmutable::create(2025, 3, 14, 10, 0, 0, 'UTC');
        $afterRange = CarbonImmutable::create(2025, 7, 21, 10, 0, 0, 'UTC');
        $onStartBoundary = CarbonImmutable::create(2025, 3, 15, 0, 0, 0, 'UTC');
        $onEndBoundary = CarbonImmutable::create(2025, 7, 20, 23, 59, 59, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $insideRange, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $beforeRange, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $afterRange, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $onStartBoundary, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $onEndBoundary, $this->subject));
    }

    public function test_absolute_range_with_reversed_boundaries(): void
    {
        $config = [
            'kind' => 'absolute',
            'from' => '2025-07-20',
            'to' => '2025-03-15',
        ];

        $insideRange = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $insideRange, $this->subject));
    }

    public function test_absolute_range_includes_entire_day(): void
    {
        $config = [
            'kind' => 'absolute',
            'from' => '2025-03-15',
            'to' => '2025-03-15',
        ];

        $earlyMorning = CarbonImmutable::create(2025, 3, 15, 0, 0, 1, 'UTC');
        $lateNight = CarbonImmutable::create(2025, 3, 15, 23, 59, 58, 'UTC');
        $nextDay = CarbonImmutable::create(2025, 3, 16, 0, 0, 1, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $earlyMorning, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $lateNight, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $nextDay, $this->subject));
    }

    public function test_absolute_range_with_null_from(): void
    {
        $config = [
            'kind' => 'absolute',
            'from' => null,
            'to' => '2025-07-20',
        ];

        $moment = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_absolute_range_with_null_to(): void
    {
        $config = [
            'kind' => 'absolute',
            'from' => '2025-03-15',
            'to' => null,
        ];

        $moment = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_absolute_range_with_empty_string_boundaries(): void
    {
        $config = [
            'kind' => 'absolute',
            'from' => '',
            'to' => '',
        ];

        $moment = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_absolute_range_with_invalid_date_format(): void
    {
        $config = [
            'kind' => 'absolute',
            'from' => 'invalid-date',
            'to' => '2025-07-20',
        ];

        $moment = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_default_kind_is_absolute(): void
    {
        $config = [
            'from' => '2025-03-15',
            'to' => '2025-07-20',
        ];

        $insideRange = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $insideRange, $this->subject));
    }

    public function test_null_kind_defaults_to_absolute(): void
    {
        $config = [
            'kind' => null,
            'from' => '2025-03-15',
            'to' => '2025-07-20',
        ];

        $insideRange = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $insideRange, $this->subject));
    }

    public function test_invalid_kind_defaults_to_absolute(): void
    {
        $config = [
            'kind' => 'invalid-kind',
            'from' => '2025-03-15',
            'to' => '2025-07-20',
        ];

        $insideRange = CarbonImmutable::create(2025, 5, 10, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $insideRange, $this->subject));
    }

    public function test_yearly_range_with_different_timezones(): void
    {
        $config = [
            'kind' => 'yearly',
            'from' => '03-15',
            'to' => '03-16',
        ];

        $nyTime = CarbonImmutable::create(2025, 3, 15, 23, 0, 0, 'America/New_York');
        $utcTime = CarbonImmutable::create(2025, 3, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $nyTime, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $utcTime, $this->subject));
    }

    public function test_absolute_range_with_different_timezones(): void
    {
        $config = [
            'kind' => 'absolute',
            'from' => '2025-03-15',
            'to' => '2025-03-15',
        ];

        $nyTime = CarbonImmutable::create(2025, 3, 15, 23, 0, 0, 'America/New_York');
        $utcTime = CarbonImmutable::create(2025, 3, 15, 12, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $nyTime, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $utcTime, $this->subject));
    }
}
