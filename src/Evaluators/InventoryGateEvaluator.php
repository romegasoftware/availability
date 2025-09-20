<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Evaluators;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Container\Container;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;

final class InventoryGateEvaluator implements RuleEvaluator
{
    /** @var array<class-string<AvailabilitySubject>, callable|null> */
    private array $resolverCache = [];

    public function __construct(private Container $container) {}

    public function matches(array $config, CarbonInterface $at, AvailabilitySubject $subject): bool
    {
        $minimum = $this->parseMinimum($config['min'] ?? null);

        if ($minimum === null) {
            return false;
        }

        $resolver = $this->resolveForSubject($subject);

        if ($resolver === null) {
            return false;
        }

        $result = $resolver($subject, $at, $config);

        if (is_bool($result)) {
            return $result;
        }

        if (is_numeric($result)) {
            return (float) $result >= $minimum;
        }

        return false;
    }

    private function parseMinimum(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max((float) $value, 0.0);
    }

    private function resolveForSubject(AvailabilitySubject $subject): ?callable
    {
        $class = $subject::class;

        if (array_key_exists($class, $this->resolverCache)) {
            return $this->resolverCache[$class];
        }

        $config = config('availability.inventory_gate', []);
        $resolvers = is_array($config['resolvers'] ?? null) ? $config['resolvers'] : [];

        $definition = $resolvers[$class] ?? ($resolvers['*'] ?? ($config['resolver'] ?? null));

        if ($definition === null) {
            return $this->resolverCache[$class] = null;
        }

        $callable = $this->normalizeResolver($definition);

        return $this->resolverCache[$class] = $callable;
    }

    private function normalizeResolver(mixed $definition): ?callable
    {
        if (is_callable($definition)) {
            return $definition instanceof Closure
                ? $definition
                : Closure::fromCallable($definition);
        }

        if (is_string($definition)) {
            if (str_contains($definition, '@')) {
                [$class, $method] = explode('@', $definition, 2);
                $instance = $this->container->make($class);

                return is_callable([$instance, $method]) ? [$instance, $method] : null;
            }

            if (class_exists($definition)) {
                $instance = $this->container->make($definition);

                if (is_callable($instance)) {
                    return Closure::fromCallable($instance);
                }

                if (is_callable([$instance, 'resolve'])) {
                    return [$instance, 'resolve'];
                }
            }

            return null;
        }

        if (is_array($definition) && count($definition) === 2) {
            [$target, $method] = $definition;

            if (is_object($target) && is_callable([$target, $method])) {
                return [$target, $method];
            }

            if (is_string($target) && class_exists($target)) {
                $instance = $this->container->make($target);

                return is_callable([$instance, $method]) ? [$instance, $method] : null;
            }
        }

        return null;
    }
}
