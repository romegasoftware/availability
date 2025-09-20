<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Support;

use Illuminate\Contracts\Container\Container;
use RomegaSoftware\Availability\Contracts\RuleEvaluator;

final class RuleEvaluatorRegistry
{
    /** @var array<string, RuleEvaluator|string|callable(Container): RuleEvaluator> */
    private array $definitions = [];

    /** @var array<string, RuleEvaluator> */
    private array $resolved = [];

    public function __construct(private Container $container, array $definitions = [])
    {
        foreach ($definitions as $type => $definition) {
            $this->register($type, $definition);
        }
    }

    public function register(string $type, RuleEvaluator|string|callable $definition): void
    {
        unset($this->resolved[$type]);
        $this->definitions[$type] = $definition;
    }

    public function get(string $type): ?RuleEvaluator
    {
        if (isset($this->resolved[$type])) {
            return $this->resolved[$type];
        }

        if (! array_key_exists($type, $this->definitions)) {
            return null;
        }

        $definition = $this->definitions[$type];

        $instance = match (true) {
            $definition instanceof RuleEvaluator => $definition,
            is_string($definition) => $this->container->make($definition),
            is_callable($definition) => $definition($this->container),
            default => null,
        };

        if ($instance === null) {
            return null;
        }

        return $this->resolved[$type] = $instance;
    }

    /** @return array<string, RuleEvaluator> */
    public function all(): array
    {
        foreach (array_keys($this->definitions) as $type) {
            $this->get($type);
        }

        return $this->resolved;
    }
}
