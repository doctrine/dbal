<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use Symfony\Contracts\Service\ServiceProviderInterface;

use function sprintf;

/**
 * A simple service provider that records how many times each service is resolved.
 *
 * Used to assert that {@see \Doctrine\DBAL\Types\TypeRegistry} resolves provider-backed types
 * lazily and only once. Each service ID is the type name.
 *
 * @implements ServiceProviderInterface<Type>
 */
final class CountingContainer implements ServiceProviderInterface
{
    /** @var array<string, int> */
    public array $resolved = [];

    /** @param array<string, Type> $services */
    public function __construct(private array $services = [])
    {
    }

    /** @return array<string, class-string<Type>> */
    public function getProvidedServices(): array
    {
        $provided = [];
        foreach ($this->services as $id => $type) {
            $provided[$id] = $type::class;
        }

        return $provided;
    }

    public function get(string $id): Type
    {
        if (! isset($this->services[$id])) {
            throw new InvalidArgumentException(sprintf('Service "%s" not found.', $id));
        }

        $this->resolved[$id] = ($this->resolved[$id] ?? 0) + 1;

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
