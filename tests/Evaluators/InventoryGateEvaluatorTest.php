<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Evaluators;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Evaluators\InventoryGateEvaluator;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Tests\Stubs\TestAvailabilitySubject;
use RomegaSoftware\Availability\Tests\TestCase;
use stdClass;

final class InventoryGateEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private InventoryGateEvaluator $evaluator;

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

        $this->evaluator = new InventoryGateEvaluator(app());
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

    public function test_no_minimum_returns_false(): void
    {
        $config = [];
        $moment = CarbonImmutable::now();

        $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
    }

    public function test_invalid_minimum_returns_false(): void
    {
        $configs = [
            ['min' => 'invalid'],
            ['min' => null],
            ['min' => []],
            ['min' => new stdClass],
        ];

        $moment = CarbonImmutable::now();

        foreach ($configs as $config) {
            $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
        }
    }

    public function test_negative_minimum_becomes_zero(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => function () {
                return 1;
            },
        ]);

        try {
            $config = ['min' => -5];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_no_resolver_configured_returns_false(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', []);

        try {
            $config = ['min' => 1];
            $moment = CarbonImmutable::now();

            $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_resolver_returning_boolean_true(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => function () {
                return true;
            },
        ]);

        try {
            $config = ['min' => 1];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_resolver_returning_boolean_false(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => function () {
                return false;
            },
        ]);

        try {
            $config = ['min' => 1];
            $moment = CarbonImmutable::now();

            $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_resolver_returning_numeric_above_minimum(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => function () {
                return 10;
            },
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_resolver_returning_numeric_below_minimum(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => function () {
                return 3;
            },
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_resolver_returning_numeric_equal_to_minimum(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => function () {
                return 5;
            },
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_resolver_returning_non_numeric_non_boolean(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => function () {
                return 'invalid';
            },
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_class_specific_resolver(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolvers' => [
                TestAvailabilitySubject::class => function () {
                    return 100;
                },
                '*' => function () {
                    return 0;
                },
            ],
        ]);

        try {
            $config = ['min' => 50];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_wildcard_resolver_fallback(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolvers' => [
                'SomeOtherClass' => function () {
                    return 0;
                },
                '*' => function () {
                    return 100;
                },
            ],
        ]);

        try {
            $config = ['min' => 50];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_string_resolver_with_at_notation(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => TestInventoryResolver::class.'@resolve',
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_string_resolver_with_invokable_class(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => TestInventoryInvokableResolver::class,
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_string_resolver_with_resolve_method(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => TestInventoryResolver::class,
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_array_resolver_with_class_and_method(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => [TestInventoryResolver::class, 'customMethod'],
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_array_resolver_with_instance_and_method(): void
    {
        $previous = config('availability.inventory_gate');

        $resolver = new TestInventoryResolver;

        config()->set('availability.inventory_gate', [
            'resolver' => [$resolver, 'resolve'],
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_invalid_string_resolver_returns_null(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => 'InvalidClass',
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->assertFalse($this->evaluator->matches($config, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_resolver_receives_correct_parameters(): void
    {
        $previous = config('availability.inventory_gate');

        $receivedSubject = null;
        $receivedMoment = null;
        $receivedConfig = null;

        config()->set('availability.inventory_gate', [
            'resolver' => function (AvailabilitySubject $subject, $moment, $config) use (&$receivedSubject, &$receivedMoment, &$receivedConfig) {
                $receivedSubject = $subject;
                $receivedMoment = $moment;
                $receivedConfig = $config;

                return 10;
            },
        ]);

        try {
            $config = ['min' => 5, 'custom' => 'value'];
            $moment = CarbonImmutable::create(2025, 1, 15, 10, 0, 0, 'UTC');

            $this->evaluator->matches($config, $moment, $this->subject);

            $this->assertSame($this->subject, $receivedSubject);
            $this->assertEquals($moment, $receivedMoment);
            $this->assertEquals($config, $receivedConfig);
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_resolver_caching(): void
    {
        $previous = config('availability.inventory_gate');

        $callCount = 0;

        config()->set('availability.inventory_gate', [
            'resolver' => function () use (&$callCount) {
                $callCount++;

                return 10;
            },
        ]);

        try {
            $config = ['min' => 5];
            $moment = CarbonImmutable::now();

            $this->evaluator->matches($config, $moment, $this->subject);
            $this->evaluator->matches($config, $moment, $this->subject);
            $this->evaluator->matches($config, $moment, $this->subject);

            $this->assertEquals(3, $callCount);
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }

    public function test_float_minimum_and_return_values(): void
    {
        $previous = config('availability.inventory_gate');

        config()->set('availability.inventory_gate', [
            'resolver' => function () {
                return 5.5;
            },
        ]);

        try {
            $config1 = ['min' => 5.4];
            $config2 = ['min' => 5.6];
            $moment = CarbonImmutable::now();

            $this->assertTrue($this->evaluator->matches($config1, $moment, $this->subject));
            $this->assertFalse($this->evaluator->matches($config2, $moment, $this->subject));
        } finally {
            config()->set('availability.inventory_gate', $previous);
        }
    }
}

class TestInventoryResolver
{
    public function resolve(): int
    {
        return 10;
    }

    public function customMethod(): int
    {
        return 10;
    }
}

class TestInventoryInvokableResolver
{
    public function __invoke(): int
    {
        return 10;
    }
}
