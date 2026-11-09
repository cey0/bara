<?php

declare(strict_types=1);

namespace Framework;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

/**
 * Service container: bindings, singletons, and constructor injection.
 * Controllers are resolved through it, so dependencies are autowired.
 */
final class Container
{
    /** @var array<string, array{concrete: callable|null, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function bind(string $abstract, ?callable $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'shared' => $shared];
    }

    public function singleton(string $abstract, ?callable $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Resolve a class or binding. Unbound classes are built via reflection.
     */
    public function make(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;
        $concrete = $binding['concrete'] ?? null;

        $object = $concrete === null ? $this->build($abstract) : $concrete($this);

        if (($binding['shared'] ?? false) === true) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Call a callable, injecting route parameters by name and dependencies by type.
     */
    public function call($callable, array $parameters = [])
    {
        if (is_array($callable) && is_string($callable[0])) {
            $callable = [$this->make($callable[0]), $callable[1]];
        }

        $reflection = is_array($callable)
            ? new ReflectionMethod($callable[0], $callable[1])
            : new ReflectionFunction($callable);

        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $arguments[] = $this->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException(sprintf(
                'Unable to resolve parameter $%s while calling %s().',
                $name,
                $reflection->getName()
            ));
        }

        return $callable(...$arguments);
    }

    private function build(string $class)
    {
        if (! class_exists($class)) {
            throw new RuntimeException("Class [$class] does not exist.");
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            throw new RuntimeException("Class [$class] is not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $arguments[] = $this->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw new RuntimeException(sprintf(
                'Cannot autowire $%s in %s::__construct(). Bind it in the container.',
                $parameter->getName(),
                $class
            ));
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
