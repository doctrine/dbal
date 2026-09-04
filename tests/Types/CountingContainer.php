<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Types\Type;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * A plain PSR-11 container that records how many times each service is resolved.
 *
 * Used to assert that {@see \Doctrine\DBAL\Types\TypeRegistry} resolves container-backed types
 * lazily and only once.
 */
final class CountingContainer implements ContainerInterface
{
    /** @var array<string, int> */
    public array $resolved = [];

    /** @param array<string, Type> $services */
    public function __construct(private array $services = [])
    {
    }

    public function get(string $id): Type
    {
        if (! isset($this->services[$id])) {
            throw new class extends RuntimeException implements NotFoundExceptionInterface {
            };
        }

        $this->resolved[$id] = ($this->resolved[$id] ?? 0) + 1;

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
