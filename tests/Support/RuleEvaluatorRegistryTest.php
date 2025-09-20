<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Support;

use Illuminate\Container\Container;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;
use RomegaSoftware\Availability\Evaluators\BlackoutDateEvaluator;
use RomegaSoftware\Availability\Evaluators\WeekdaysEvaluator;
use RomegaSoftware\Availability\Support\RuleEvaluatorRegistry;
use RomegaSoftware\Availability\Tests\TestCase;

final class RuleEvaluatorRegistryTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container;
    }

    public function test_constructor_with_empty_definitions(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);

        $this->assertNull($registry->get('nonexistent'));
        $this->assertEmpty($registry->all());
    }

    public function test_constructor_with_initial_definitions(): void
    {
        $evaluator = new BlackoutDateEvaluator;
        $definitions = [
            'blackout' => $evaluator,
            'weekdays' => WeekdaysEvaluator::class,
        ];

        $registry = new RuleEvaluatorRegistry($this->container, $definitions);

        $this->assertSame($evaluator, $registry->get('blackout'));
        $this->assertInstanceOf(WeekdaysEvaluator::class, $registry->get('weekdays'));
    }

    public function test_register_with_evaluator_instance(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);
        $evaluator = new BlackoutDateEvaluator;

        $registry->register('blackout', $evaluator);

        $this->assertSame($evaluator, $registry->get('blackout'));
    }

    public function test_register_with_string_class_name(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);

        $registry->register('weekdays', WeekdaysEvaluator::class);

        $result = $registry->get('weekdays');
        $this->assertInstanceOf(WeekdaysEvaluator::class, $result);
    }

    public function test_register_with_callable(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);
        $evaluator = new BlackoutDateEvaluator;

        $registry->register('custom', function (Container $container) use ($evaluator): RuleEvaluator {
            return $evaluator;
        });

        $this->assertSame($evaluator, $registry->get('custom'));
    }

    public function test_register_overwrites_existing_definition(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);
        $firstEvaluator = new BlackoutDateEvaluator;
        $secondEvaluator = new WeekdaysEvaluator;

        $registry->register('test', $firstEvaluator);
        $this->assertSame($firstEvaluator, $registry->get('test'));

        $registry->register('test', $secondEvaluator);
        $this->assertSame($secondEvaluator, $registry->get('test'));
    }

    public function test_register_clears_resolved_cache(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);
        $firstEvaluator = new BlackoutDateEvaluator;
        $secondEvaluator = new WeekdaysEvaluator;

        // Register and resolve first evaluator
        $registry->register('test', $firstEvaluator);
        $result1 = $registry->get('test');
        $this->assertSame($firstEvaluator, $result1);

        // Register new evaluator and verify cache is cleared
        $registry->register('test', $secondEvaluator);
        $result2 = $registry->get('test');
        $this->assertSame($secondEvaluator, $result2);
        $this->assertNotSame($result1, $result2);
    }

    public function test_get_returns_null_for_nonexistent_type(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);

        $this->assertNull($registry->get('nonexistent'));
    }

    public function test_get_caches_resolved_instances(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);
        $registry->register('weekdays', WeekdaysEvaluator::class);

        $first = $registry->get('weekdays');
        $second = $registry->get('weekdays');

        $this->assertSame($first, $second);
    }

    public function test_get_with_callable_definition(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);
        $callCount = 0;
        $evaluator = new BlackoutDateEvaluator;

        $registry->register('callable', function (Container $container) use ($evaluator, &$callCount): RuleEvaluator {
            $callCount++;

            return $evaluator;
        });

        $first = $registry->get('callable');
        $second = $registry->get('callable');

        $this->assertSame($evaluator, $first);
        $this->assertSame($first, $second);
        $this->assertEquals(1, $callCount, 'Callable should only be invoked once due to caching');
    }

    public function test_get_with_invalid_callable_returning_null(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);

        $registry->register('invalid', function (): ?RuleEvaluator {
            return null;
        });

        $this->assertNull($registry->get('invalid'));
    }

    public function test_get_with_callable_returning_wrong_type_throws_error(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);

        $registry->register('invalid', function (): string {
            return 'not an evaluator';
        });

        $this->expectException(\TypeError::class);
        $registry->get('invalid');
    }

    public function test_all_returns_empty_array_when_no_definitions(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);

        $this->assertEmpty($registry->all());
    }

    public function test_all_resolves_all_definitions(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);
        $blackoutEvaluator = new BlackoutDateEvaluator;

        $registry->register('blackout', $blackoutEvaluator);
        $registry->register('weekdays', WeekdaysEvaluator::class);

        $all = $registry->all();

        $this->assertCount(2, $all);
        $this->assertSame($blackoutEvaluator, $all['blackout']);
        $this->assertInstanceOf(WeekdaysEvaluator::class, $all['weekdays']);
    }

    public function test_all_skips_invalid_definitions(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);
        $validEvaluator = new BlackoutDateEvaluator;

        $registry->register('valid', $validEvaluator);
        $registry->register('invalid', function (): ?RuleEvaluator {
            return null;
        });

        $all = $registry->all();

        $this->assertCount(1, $all);
        $this->assertSame($validEvaluator, $all['valid']);
        $this->assertArrayNotHasKey('invalid', $all);
    }

    public function test_all_maintains_resolution_cache(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);
        $registry->register('weekdays', WeekdaysEvaluator::class);

        // First call to all() should resolve the evaluator
        $all1 = $registry->all();

        // Second call to all() should return the same cached instance
        $all2 = $registry->all();

        $this->assertSame($all1['weekdays'], $all2['weekdays']);

        // Direct get() should also return the same cached instance
        $direct = $registry->get('weekdays');
        $this->assertSame($all1['weekdays'], $direct);
    }

    public function test_get_with_invalid_definition_type(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);

        // Use reflection to directly set an invalid definition type
        $reflection = new \ReflectionClass($registry);
        $definitionsProperty = $reflection->getProperty('definitions');
        $definitionsProperty->setAccessible(true);

        $definitions = $definitionsProperty->getValue($registry);
        $definitions['invalid'] = 123; // Invalid type - not RuleEvaluator, string, or callable
        $definitionsProperty->setValue($registry, $definitions);

        $this->assertNull($registry->get('invalid'));
    }

    public function test_complex_scenario_with_mixed_definition_types(): void
    {
        $registry = new RuleEvaluatorRegistry($this->container);
        $instanceEvaluator = new BlackoutDateEvaluator;

        // Register different types of definitions
        $registry->register('instance', $instanceEvaluator);
        $registry->register('class', WeekdaysEvaluator::class);
        $registry->register('callable', fn (Container $c) => new BlackoutDateEvaluator);

        // Test individual retrieval
        $this->assertSame($instanceEvaluator, $registry->get('instance'));
        $this->assertInstanceOf(WeekdaysEvaluator::class, $registry->get('class'));
        $this->assertInstanceOf(BlackoutDateEvaluator::class, $registry->get('callable'));

        // Test all() method
        $all = $registry->all();
        $this->assertCount(3, $all);
        $this->assertSame($instanceEvaluator, $all['instance']);
        $this->assertInstanceOf(WeekdaysEvaluator::class, $all['class']);
        $this->assertInstanceOf(BlackoutDateEvaluator::class, $all['callable']);

        // Verify caching works across methods
        $this->assertSame($all['class'], $registry->get('class'));
        $this->assertSame($all['callable'], $registry->get('callable'));
    }
}
