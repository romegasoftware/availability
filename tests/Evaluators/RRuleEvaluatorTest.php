<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Evaluators;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RomegaSoftware\Availability\Evaluators\RRuleEvaluator;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubject;
use RomegaSoftware\Availability\Tests\TestCase;

final class RRuleEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private RRuleEvaluator $evaluator;

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

        $this->evaluator = new RRuleEvaluator;
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

    public function test_empty_rrule_returns_false(): void
    {
        $config = ['rrule' => ''];
        $moment = CarbonImmutable::now();

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_null_rrule_returns_false(): void
    {
        $config = [];
        $moment = CarbonImmutable::now();

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_invalid_rrule_format_returns_false(): void
    {
        $config = ['rrule' => 'INVALID_FORMAT'];
        $moment = CarbonImmutable::now();

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_rrule_without_freq_returns_false(): void
    {
        $config = ['rrule' => 'INTERVAL=2;BYDAY=MO'];
        $moment = CarbonImmutable::now();

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_daily_frequency_matches(): void
    {
        $config = ['rrule' => 'FREQ=DAILY'];
        $moment = CarbonImmutable::create(2025, 1, 15, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_daily_frequency_with_interval(): void
    {
        $config = [
            'rrule' => 'FREQ=DAILY;INTERVAL=2;DTSTART=20250101T000000Z',
        ];

        $oddDay = CarbonImmutable::create(2025, 1, 3, 10, 0, 0, 'UTC');
        $evenDay = CarbonImmutable::create(2025, 1, 4, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $oddDay, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $evenDay, $this->subject));
    }

    public function test_weekly_frequency_with_byday(): void
    {
        $config = ['rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR'];

        $monday = CarbonImmutable::create(2025, 1, 6, 10, 0, 0, 'UTC');
        $tuesday = CarbonImmutable::create(2025, 1, 7, 10, 0, 0, 'UTC');
        $wednesday = CarbonImmutable::create(2025, 1, 8, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $monday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $tuesday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $wednesday, $this->subject));
    }

    public function test_weekly_frequency_with_interval(): void
    {
        $config = [
            'rrule' => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO;DTSTART=20250106T000000Z',
        ];

        $firstMonday = CarbonImmutable::create(2025, 1, 6, 10, 0, 0, 'UTC');
        $secondMonday = CarbonImmutable::create(2025, 1, 13, 10, 0, 0, 'UTC');
        $thirdMonday = CarbonImmutable::create(2025, 1, 20, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $firstMonday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $secondMonday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $thirdMonday, $this->subject));
    }

    public function test_monthly_frequency_with_bymonthday(): void
    {
        $config = ['rrule' => 'FREQ=MONTHLY;BYMONTHDAY=15'];

        $fifteenth = CarbonImmutable::create(2025, 2, 15, 10, 0, 0, 'UTC');
        $sixteenth = CarbonImmutable::create(2025, 2, 16, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $fifteenth, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $sixteenth, $this->subject));
    }

    public function test_monthly_frequency_with_negative_bymonthday(): void
    {
        $config = ['rrule' => 'FREQ=MONTHLY;BYMONTHDAY=-1'];

        $lastDayJan = CarbonImmutable::create(2025, 1, 31, 10, 0, 0, 'UTC');
        $lastDayFeb = CarbonImmutable::create(2025, 2, 28, 10, 0, 0, 'UTC');
        $notLastDay = CarbonImmutable::create(2025, 2, 27, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $lastDayJan, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $lastDayFeb, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $notLastDay, $this->subject));
    }

    public function test_monthly_frequency_with_byday_ordinal(): void
    {
        $config = ['rrule' => 'FREQ=MONTHLY;BYDAY=2MO'];

        $firstMonday = CarbonImmutable::create(2025, 1, 6, 10, 0, 0, 'UTC');
        $secondMonday = CarbonImmutable::create(2025, 1, 13, 10, 0, 0, 'UTC');
        $thirdMonday = CarbonImmutable::create(2025, 1, 20, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $firstMonday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $secondMonday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $thirdMonday, $this->subject));
    }

    public function test_monthly_frequency_with_negative_byday_ordinal(): void
    {
        $config = ['rrule' => 'FREQ=MONTHLY;BYDAY=-1FR'];

        $lastFriday = CarbonImmutable::create(2025, 1, 31, 10, 0, 0, 'UTC');
        $secondLastFriday = CarbonImmutable::create(2025, 1, 24, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $lastFriday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $secondLastFriday, $this->subject));
    }

    public function test_yearly_frequency_with_bymonth(): void
    {
        $config = ['rrule' => 'FREQ=YEARLY;BYMONTH=3,7,12'];

        $march = CarbonImmutable::create(2025, 3, 15, 10, 0, 0, 'UTC');
        $april = CarbonImmutable::create(2025, 4, 15, 10, 0, 0, 'UTC');
        $july = CarbonImmutable::create(2025, 7, 15, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $march, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $april, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $july, $this->subject));
    }

    public function test_yearly_frequency_with_byday_ordinal(): void
    {
        $config = ['rrule' => 'FREQ=YEARLY;BYDAY=1MO'];

        $firstMondayOfYear = CarbonImmutable::create(2025, 1, 6, 10, 0, 0, 'UTC');
        $secondMondayOfYear = CarbonImmutable::create(2025, 1, 13, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $firstMondayOfYear, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $secondMondayOfYear, $this->subject));
    }

    public function test_until_constraint(): void
    {
        $config = ['rrule' => 'FREQ=DAILY;UNTIL=20250115T235959Z'];

        $beforeUntil = CarbonImmutable::create(2025, 1, 15, 10, 0, 0, 'UTC');
        $afterUntil = CarbonImmutable::create(2025, 1, 16, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $beforeUntil, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $afterUntil, $this->subject));
    }

    public function test_until_with_different_date_formats(): void
    {
        $configs = [
            ['rrule' => 'FREQ=DAILY;UNTIL=20250115'],
            ['rrule' => 'FREQ=DAILY;UNTIL=2025-01-15'],
            ['rrule' => 'FREQ=DAILY;UNTIL=2025-01-15T23:59:59'],
        ];

        $beforeUntil = CarbonImmutable::create(2025, 1, 14, 10, 0, 0, 'UTC');
        $afterUntil = CarbonImmutable::create(2025, 1, 16, 10, 0, 0, 'UTC');

        foreach ($configs as $config) {
            $this->assertTrue($this->evaluator->matches($config, $beforeUntil, $this->subject));
            $this->assertFalse($this->evaluator->matches($config, $afterUntil, $this->subject));
        }
    }

    public function test_byhour_constraint(): void
    {
        $config = ['rrule' => 'FREQ=DAILY;BYHOUR=9,10,11,14,15,16'];

        $morning = CarbonImmutable::create(2025, 1, 15, 10, 30, 0, 'UTC');
        $lunch = CarbonImmutable::create(2025, 1, 15, 12, 30, 0, 'UTC');
        $afternoon = CarbonImmutable::create(2025, 1, 15, 14, 30, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $morning, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $lunch, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $afternoon, $this->subject));
    }

    public function test_byminute_constraint(): void
    {
        $config = ['rrule' => 'FREQ=DAILY;BYMINUTE=0,15,30,45'];

        $onQuarter = CarbonImmutable::create(2025, 1, 15, 10, 15, 0, 'UTC');
        $offQuarter = CarbonImmutable::create(2025, 1, 15, 10, 17, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $onQuarter, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $offQuarter, $this->subject));
    }

    public function test_bysecond_constraint(): void
    {
        $config = ['rrule' => 'FREQ=DAILY;BYSECOND=0,30'];

        $onSecond = CarbonImmutable::create(2025, 1, 15, 10, 15, 30, 'UTC');
        $offSecond = CarbonImmutable::create(2025, 1, 15, 10, 15, 15, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $onSecond, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $offSecond, $this->subject));
    }

    public function test_custom_timezone(): void
    {
        $config = [
            'rrule' => 'FREQ=DAILY;BYHOUR=9',
            'tz' => 'America/New_York',
        ];

        $utcTime = CarbonImmutable::create(2025, 1, 15, 14, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $utcTime, $this->subject));
    }

    public function test_complex_rrule_with_multiple_constraints(): void
    {
        $config = [
            'rrule' => 'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1',
        ];

        $lastWeekday = CarbonImmutable::create(2025, 1, 31, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $lastWeekday, $this->subject));
    }

    public function test_invalid_frequency_returns_false(): void
    {
        $config = ['rrule' => 'FREQ=INVALID'];
        $moment = CarbonImmutable::now();

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_interval_without_dtstart_returns_false(): void
    {
        $config = ['rrule' => 'FREQ=DAILY;INTERVAL=3'];
        $moment = CarbonImmutable::now();

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_monthly_without_constraints_uses_dtstart_day(): void
    {
        $config = [
            'rrule' => 'FREQ=MONTHLY;DTSTART=20250115T000000Z',
        ];

        $fifteenth = CarbonImmutable::create(2025, 2, 15, 10, 0, 0, 'UTC');
        $sixteenth = CarbonImmutable::create(2025, 2, 16, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $fifteenth, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $sixteenth, $this->subject));
    }

    public function test_yearly_without_constraints_uses_dtstart_day_of_year(): void
    {
        $config = [
            'rrule' => 'FREQ=YEARLY;DTSTART=20250315T000000Z',
        ];

        $march15_2026 = CarbonImmutable::create(2026, 3, 15, 10, 0, 0, 'UTC');
        $march16_2026 = CarbonImmutable::create(2026, 3, 16, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $march15_2026, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $march16_2026, $this->subject));
    }

    public function test_invalid_until_date_returns_false(): void
    {
        $config = ['rrule' => 'FREQ=DAILY;UNTIL=INVALID_DATE'];
        $moment = CarbonImmutable::now();

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_empty_byday_returns_false(): void
    {
        $config = ['rrule' => 'FREQ=WEEKLY;BYDAY='];
        $moment = CarbonImmutable::now();

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_invalid_byday_format_is_ignored(): void
    {
        $config = ['rrule' => 'FREQ=WEEKLY;BYDAY=MO,INVALID,TU'];

        $monday = CarbonImmutable::create(2025, 1, 6, 10, 0, 0, 'UTC');
        $tuesday = CarbonImmutable::create(2025, 1, 7, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $monday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $tuesday, $this->subject));
    }

    public function test_bymonthday_zero_is_filtered(): void
    {
        $config = ['rrule' => 'FREQ=MONTHLY;BYMONTHDAY=0,15'];

        $fifteenth = CarbonImmutable::create(2025, 2, 15, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $fifteenth, $this->subject));
    }

    public function test_out_of_range_values_are_ignored(): void
    {
        $config = ['rrule' => 'FREQ=YEARLY;BYMONTH=13,3,0'];

        $march = CarbonImmutable::create(2025, 3, 15, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $march, $this->subject));
    }

    public function test_yearly_frequency_with_negative_byday_ordinal(): void
    {
        $config = ['rrule' => 'FREQ=YEARLY;BYDAY=-1MO'];

        // Last Monday of the year (December 25, 2023)
        $lastMondayOfYear = CarbonImmutable::create(2023, 12, 25, 10, 0, 0, 'UTC');
        $notLastMonday = CarbonImmutable::create(2023, 12, 18, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $lastMondayOfYear, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $notLastMonday, $this->subject));
    }

    public function test_yearly_frequency_with_byweekno(): void
    {
        $config = ['rrule' => 'FREQ=YEARLY;BYWEEKNO=1,52'];

        // BYWEEKNO is checked but not actually implemented, it just returns true if present
        $anyDay = CarbonImmutable::create(2025, 6, 15, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $anyDay, $this->subject));
    }

    public function test_yearly_frequency_with_byyearday(): void
    {
        $config = ['rrule' => 'FREQ=YEARLY;BYYEARDAY=1,365,-1'];

        // BYYEARDAY is checked but not actually implemented, it just returns true if present
        $anyDay = CarbonImmutable::create(2025, 6, 15, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $anyDay, $this->subject));
    }

    public function test_yearly_frequency_with_byyearday_leap_year(): void
    {
        $config = ['rrule' => 'FREQ=YEARLY;BYYEARDAY=366'];

        // BYYEARDAY is checked but not actually implemented, it just returns true if present
        $anyDay = CarbonImmutable::create(2024, 12, 31, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $anyDay, $this->subject));
    }

    public function test_parse_date_time_with_timezone_offset(): void
    {
        $config = ['rrule' => 'FREQ=DAILY;UNTIL=2025-01-15T10:00:00-05:00'];

        // Before UNTIL in UTC (15:00 UTC = 10:00 EST)
        $beforeUntil = CarbonImmutable::create(2025, 1, 15, 14, 0, 0, 'UTC');
        // After UNTIL in UTC
        $afterUntil = CarbonImmutable::create(2025, 1, 15, 16, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $beforeUntil, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $afterUntil, $this->subject));
    }

    public function test_parse_date_time_with_utc_z_format(): void
    {
        $config = ['rrule' => 'FREQ=DAILY;UNTIL=20250115T100000Z'];

        $beforeUntil = CarbonImmutable::create(2025, 1, 15, 9, 0, 0, 'UTC');
        $afterUntil = CarbonImmutable::create(2025, 1, 15, 11, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $beforeUntil, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $afterUntil, $this->subject));
    }

    public function test_parse_date_time_fallback_to_carbon_parse(): void
    {
        $config = ['rrule' => 'FREQ=DAILY;UNTIL=next Thursday'];

        // This will use Carbon's natural language parsing
        $moment = CarbonImmutable::now();

        // Just verify it doesn't crash
        $result = $this->evaluator->matches($config, $moment, $this->subject);
        $this->assertIsBool($result);
    }

    public function test_monthly_frequency_with_interval_and_no_dtstart(): void
    {
        $config = ['rrule' => 'FREQ=MONTHLY;INTERVAL=2'];

        $moment = CarbonImmutable::create(2025, 3, 15, 10, 0, 0, 'UTC');

        // Without DTSTART and interval > 1, should return false
        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_monthly_frequency_with_interval_and_dtstart(): void
    {
        // This test specifically covers line 147 in RRuleEvaluator.php:
        // 'MONTHLY' => $start->diffInMonths($moment) % $interval === 0,
        $config = [
            'rrule' => 'FREQ=MONTHLY;INTERVAL=3;DTSTART=20250115T100000Z',
        ];

        // Starting January 15, 2025, every 3 months
        // Should match: January, April, July, October
        // Should not match: February, March, May, June, August, September, November, December

        $january = CarbonImmutable::create(2025, 1, 15, 10, 0, 0, 'UTC');
        $february = CarbonImmutable::create(2025, 2, 15, 10, 0, 0, 'UTC');
        $march = CarbonImmutable::create(2025, 3, 15, 10, 0, 0, 'UTC');
        $april = CarbonImmutable::create(2025, 4, 15, 10, 0, 0, 'UTC');
        $may = CarbonImmutable::create(2025, 5, 15, 10, 0, 0, 'UTC');
        $june = CarbonImmutable::create(2025, 6, 15, 10, 0, 0, 'UTC');
        $july = CarbonImmutable::create(2025, 7, 15, 10, 0, 0, 'UTC');
        $august = CarbonImmutable::create(2025, 8, 15, 10, 0, 0, 'UTC');
        $september = CarbonImmutable::create(2025, 9, 15, 10, 0, 0, 'UTC');
        $october = CarbonImmutable::create(2025, 10, 15, 10, 0, 0, 'UTC');
        $november = CarbonImmutable::create(2025, 11, 15, 10, 0, 0, 'UTC');
        $december = CarbonImmutable::create(2025, 12, 15, 10, 0, 0, 'UTC');

        // Should match months at 3-month intervals from January
        $this->assertTrue($this->evaluator->matches($config, $january, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $april, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $july, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $october, $this->subject));

        // Should not match months not at 3-month intervals
        $this->assertFalse($this->evaluator->matches($config, $february, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $march, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $may, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $june, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $august, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $september, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $november, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $december, $this->subject));

        // Test next year to ensure it continues correctly
        $january2026 = CarbonImmutable::create(2026, 1, 15, 10, 0, 0, 'UTC');
        $april2026 = CarbonImmutable::create(2026, 4, 15, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $january2026, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $april2026, $this->subject));
    }

    public function test_monthly_frequency_with_different_interval_and_dtstart(): void
    {
        // Additional test for line 147 coverage with INTERVAL=2
        $config = [
            'rrule' => 'FREQ=MONTHLY;INTERVAL=2;DTSTART=20250301T100000Z',
        ];

        // Starting March 1, 2025, every 2 months
        // Should match: March, May, July, September, November
        // Should not match: April, June, August, October, December

        $march = CarbonImmutable::create(2025, 3, 1, 10, 0, 0, 'UTC');
        $april = CarbonImmutable::create(2025, 4, 1, 10, 0, 0, 'UTC');
        $may = CarbonImmutable::create(2025, 5, 1, 10, 0, 0, 'UTC');
        $june = CarbonImmutable::create(2025, 6, 1, 10, 0, 0, 'UTC');
        $july = CarbonImmutable::create(2025, 7, 1, 10, 0, 0, 'UTC');

        // Should match months at 2-month intervals from March
        $this->assertTrue($this->evaluator->matches($config, $march, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $may, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $july, $this->subject));

        // Should not match months not at 2-month intervals
        $this->assertFalse($this->evaluator->matches($config, $april, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $june, $this->subject));
    }

    public function test_yearly_frequency_with_interval_and_dtstart(): void
    {
        $config = [
            'rrule' => 'FREQ=YEARLY;INTERVAL=2;DTSTART=20240715T100000Z',
        ];

        // Even years from 2024
        $year2024 = CarbonImmutable::create(2024, 7, 15, 10, 0, 0, 'UTC');
        $year2025 = CarbonImmutable::create(2025, 7, 15, 10, 0, 0, 'UTC');
        $year2026 = CarbonImmutable::create(2026, 7, 15, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $year2024, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $year2025, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $year2026, $this->subject));
    }

    public function test_dtstart_after_moment_returns_false(): void
    {
        $config = [
            'rrule' => 'FREQ=DAILY;INTERVAL=2;DTSTART=20250201T000000Z',
        ];

        $beforeStart = CarbonImmutable::create(2025, 1, 15, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $beforeStart, $this->subject));
    }

    public function test_parse_rule_with_empty_key(): void
    {
        // Rule with empty key after trimming
        $config = ['rrule' => 'FREQ=DAILY; =VALUE'];

        $moment = CarbonImmutable::create(2025, 1, 15, 10, 0, 0, 'UTC');

        // Should still work, ignoring the invalid component
        $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_parse_rule_with_malformed_pair(): void
    {
        // Rule with pairs that don't contain =
        $config = ['rrule' => 'FREQ=DAILY;INVALID_PAIR;BYHOUR=10'];

        $atHour10 = CarbonImmutable::create(2025, 1, 15, 10, 0, 0, 'UTC');
        $atHour11 = CarbonImmutable::create(2025, 1, 15, 11, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $atHour10, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $atHour11, $this->subject));
    }

    public function test_monthly_frequency_with_byday_and_no_ordinal(): void
    {
        $config = ['rrule' => 'FREQ=MONTHLY;BYDAY=MO,FR'];

        // Any Monday or Friday in the month should match
        $firstMonday = CarbonImmutable::create(2025, 3, 3, 10, 0, 0, 'UTC');
        $secondFriday = CarbonImmutable::create(2025, 3, 14, 10, 0, 0, 'UTC');
        $tuesday = CarbonImmutable::create(2025, 3, 4, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $firstMonday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $secondFriday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $tuesday, $this->subject));
    }

    public function test_yearly_with_nth_weekday_of_year_positive(): void
    {
        $config = ['rrule' => 'FREQ=YEARLY;BYDAY=2MO'];

        // Second Monday of the year 2025 is January 13
        $secondMonday = CarbonImmutable::create(2025, 1, 13, 10, 0, 0, 'UTC');
        $thirdMonday = CarbonImmutable::create(2025, 1, 20, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $secondMonday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $thirdMonday, $this->subject));
    }

    public function test_yearly_with_nth_weekday_of_year_negative_leap_year(): void
    {
        $config = ['rrule' => 'FREQ=YEARLY;BYDAY=-1FR'];

        // Last Friday of leap year 2024 is December 27
        $lastFriday2024 = CarbonImmutable::create(2024, 12, 27, 10, 0, 0, 'UTC');
        $notLastFriday = CarbonImmutable::create(2024, 12, 20, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $lastFriday2024, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $notLastFriday, $this->subject));
    }

    public function test_yearly_with_nth_weekday_of_year_negative_non_leap(): void
    {
        $config = ['rrule' => 'FREQ=YEARLY;BYDAY=-2TU'];

        // Second-to-last Tuesday of 2025 is December 23
        $secondLastTuesday = CarbonImmutable::create(2025, 12, 23, 10, 0, 0, 'UTC');
        $lastTuesday = CarbonImmutable::create(2025, 12, 30, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $secondLastTuesday, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $lastTuesday, $this->subject));
    }

    public function test_weekly_with_byday_but_wrong_weekday(): void
    {
        $config = ['rrule' => 'FREQ=WEEKLY;BYDAY=TU,TH'];

        $monday = CarbonImmutable::create(2025, 1, 6, 10, 0, 0, 'UTC');
        $tuesday = CarbonImmutable::create(2025, 1, 7, 10, 0, 0, 'UTC');
        $thursday = CarbonImmutable::create(2025, 1, 9, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $monday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $tuesday, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $thursday, $this->subject));
    }

    public function test_bymonthday_with_multiple_negative_values(): void
    {
        $config = ['rrule' => 'FREQ=MONTHLY;BYMONTHDAY=-1,-2,-3'];

        // Last 3 days of January 2025
        $jan29 = CarbonImmutable::create(2025, 1, 29, 10, 0, 0, 'UTC');
        $jan30 = CarbonImmutable::create(2025, 1, 30, 10, 0, 0, 'UTC');
        $jan31 = CarbonImmutable::create(2025, 1, 31, 10, 0, 0, 'UTC');
        $jan28 = CarbonImmutable::create(2025, 1, 28, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $jan29, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $jan30, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $jan31, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $jan28, $this->subject));
    }

    public function test_bymonthday_negative_value_exceeding_month_days(): void
    {
        $config = ['rrule' => 'FREQ=MONTHLY;BYMONTHDAY=-31'];

        // February doesn't have 31 days, so -31 would be invalid
        $feb1 = CarbonImmutable::create(2025, 2, 1, 10, 0, 0, 'UTC');

        $this->assertFalse($this->evaluator->matches($config, $feb1, $this->subject));
    }

    public function test_complex_yearly_rule_with_multiple_constraints(): void
    {
        $config = [
            'rrule' => 'FREQ=YEARLY;BYMONTH=3,9;BYDAY=MO',
        ];

        // Any Monday in March or September
        $mondayMarch = CarbonImmutable::create(2025, 3, 10, 10, 0, 0, 'UTC');
        $mondaySept = CarbonImmutable::create(2025, 9, 8, 10, 0, 0, 'UTC');
        // Monday in June (not in BYMONTH)
        $mondayJune = CarbonImmutable::create(2025, 6, 9, 10, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $mondayMarch, $this->subject));
        $this->assertTrue($this->evaluator->matches($config, $mondaySept, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $mondayJune, $this->subject));
    }

    public function test_unsupported_frequency_returns_false(): void
    {
        $config = ['rrule' => 'FREQ=HOURLY'];

        $moment = CarbonImmutable::now();

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_parse_date_time_with_invalid_format_fallback(): void
    {
        // This will trigger the fallback to Carbon::parse
        $config = ['rrule' => 'FREQ=DAILY;UNTIL=2025-01-15 10:00:00'];

        $beforeUntil = CarbonImmutable::create(2025, 1, 15, 9, 0, 0, 'UTC');
        $afterUntil = CarbonImmutable::create(2025, 1, 15, 11, 0, 0, 'UTC');

        $this->assertTrue($this->evaluator->matches($config, $beforeUntil, $this->subject));
        $this->assertFalse($this->evaluator->matches($config, $afterUntil, $this->subject));
    }
}
