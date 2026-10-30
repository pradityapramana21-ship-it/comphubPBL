<?php

declare(strict_types=1);

namespace App\Core;

final class Container
{
    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, object> */
    private array $singletons = [];

    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->singletons[$id] = $factory;
    }

    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id])
            || isset($this->singletons[$id])
            || isset($this->bindings[$id]);
    }

    public function make(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->singletons[$id])) {
            return $this->instances[$id] = ($this->singletons[$id])($this);
        }

        if (isset($this->bindings[$id])) {
            return ($this->bindings[$id])($this);
        }

        if (! class_exists($id)) {
            throw new \RuntimeException('Container cannot resolve: ' . $id);
        }

        $reflection = new \ReflectionClass($id);

        if (! $reflection->isInstantiable()) {
            throw new \RuntimeException('Container cannot instantiate: ' . $id);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
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

            throw new \RuntimeException(
                'Cannot resolve parameter $' . $parameter->getName() . ' of ' . $id,
            );
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
