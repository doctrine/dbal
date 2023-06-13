<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\ArrayParameters\Exception\MissingNamedParameter;
use Doctrine\DBAL\ArrayParameters\Exception\MissingPositionalParameter;
use Doctrine\DBAL\SQL\Parser\Visitor;
use Doctrine\DBAL\Types\Type;

use function array_fill;
use function array_key_exists;
use function count;
use function implode;
use function substr;

/** @psalm-import-type WrapperParameterTypeArray from Connection */
final class ExpandArrayParameters implements Visitor
{
    private int $originalParameterIndex = 0;

    /** @var list<string> */
    private array $convertedSQL = [];

    /** @var list<mixed> */
    private array $convertedParameters = [];

    /** @var array<int<0, max>,string|ParameterType|Type> */
    private array $convertedTypes = [];

    /**
     * @param array<int, mixed>|array<string, mixed> $parameters
     * @psalm-param WrapperParameterTypeArray $types
     */
    public function __construct(
        private readonly array $parameters,
        private readonly array $types,
    ) {
    }

    public function acceptPositionalParameter(string $sql): void
    {
        $index = $this->originalParameterIndex;

        if (! array_key_exists($index, $this->parameters)) {
            throw MissingPositionalParameter::new($index);
        }

        $this->acceptParameter($index, $this->parameters[$index]);

        $this->originalParameterIndex++;
    }

    public function acceptNamedParameter(string $sql): void
    {
        $name = substr($sql, 1);

        if (! array_key_exists($name, $this->parameters)) {
            throw MissingNamedParameter::new($name);
        }

        $this->acceptParameter($name, $this->parameters[$name]);
    }

    public function acceptOther(string $sql): void
    {
        $this->convertedSQL[] = $sql;
    }

    public function getSQL(): string
    {
        return implode('', $this->convertedSQL);
    }

    /** @return list<mixed> */
    public function getParameters(): array
    {
        return $this->convertedParameters;
    }

    private function acceptParameter(int|string $key, mixed $value): void
    {
        if (! isset($this->types[$key])) {
            $this->convertedSQL[]        = '?';
            $this->convertedParameters[] = $value;

            return;
        }

        $type = $this->types[$key];

        if (! $type instanceof ArrayParameterType) {
            $this->appendTypedParameter([$value], $type);

            return;
        }

        if (count($value) === 0) {
            $this->convertedSQL[] = 'NULL';

            return;
        }

        $this->appendTypedParameter($value, ArrayParameterType::toElementParameterType($type));
    }

    /** @return array<int<0, max>,string|ParameterType|Type> */
    public function getTypes(): array
    {
        return $this->convertedTypes;
    }

    /** @param list<mixed> $values */
    private function appendTypedParameter(array $values, string|ParameterType|Type $type): void
    {
        $this->convertedSQL[] = implode(', ', array_fill(0, count($values), '?'));

        $index = count($this->convertedParameters);

        foreach ($values as $value) {
            $this->convertedParameters[]  = $value;
            $this->convertedTypes[$index] = $type;

            $index++;
        }
    }
}
