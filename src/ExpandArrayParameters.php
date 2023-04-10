<?php

namespace Doctrine\DBAL;

use Doctrine\DBAL\ArrayParameters\Exception\MissingNamedParameter;
use Doctrine\DBAL\ArrayParameters\Exception\MissingPositionalParameter;
use Doctrine\DBAL\SQL\Parser\Visitor;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_fill;
use function array_key_exists;
use function count;
use function implode;
use function substr;

final class ExpandArrayParameters implements Visitor
{
    /** @var array<int,mixed>|array<string,mixed> */
    private array $originalParameters;

    /**
     * @var array<int|string, Type|int|string|null>
     * @psalm-var array<int|string, int|ParameterType::*|Types::*|Type|null>
     */
    private array $originalTypes;

    private int $originalParameterIndex = 0;

    /** @var list<string> */
    private array $convertedSQL = [];

    /** @var list<mixed> */
    private array $convertedParameters = [];

    /**
     * @var array<int, Type|int|string|null>
     * @psalm-var array<int, int|ParameterType::*|Types::*|Type|null>
     */
    private array $convertedTypes = [];

    /**
     * @param array<int|string, mixed>                $parameters
     * @param array<int|string, Type|int|string|null> $types
     * @psalm-param array<int|string, int|ParameterType::*|Types::*|Type|null> $types
     */
    public function __construct(array $parameters, array $types)
    {
        $this->originalParameters = $parameters;
        $this->originalTypes      = $types;
    }

    public function acceptPositionalParameter(string $sql): void
    {
        $index = $this->originalParameterIndex;

        if (! array_key_exists($index, $this->originalParameters)) {
            throw MissingPositionalParameter::new($index);
        }

        $this->acceptParameter($index, $this->originalParameters[$index]);

        $this->originalParameterIndex++;
    }

    public function acceptNamedParameter(string $sql): void
    {
        $name = substr($sql, 1);

        if (! array_key_exists($name, $this->originalParameters)) {
            throw MissingNamedParameter::new($name);
        }

        $this->acceptParameter($name, $this->originalParameters[$name]);
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

    /**
     * @param int|string $key
     * @param mixed      $value
     */
    private function acceptParameter($key, $value): void
    {
        if (! isset($this->originalTypes[$key])) {
            $this->convertedSQL[]        = '?';
            $this->convertedParameters[] = $value;

            return;
        }

        $type = $this->originalTypes[$key];

        if (
            $type !== ArrayParameterType::INTEGER
            && $type !== ArrayParameterType::STRING
            && $type !== ArrayParameterType::ASCII
        ) {
            $this->appendTypedParameter([$value], $type);

            return;
        }

        if (count($value) === 0) {
            $this->convertedSQL[] = 'NULL';

            return;
        }

        $this->appendTypedParameter($value, ArrayParameterType::toElementParameterType($type));
    }

    /**
     * @return array<int, Type|int|string|null>
     * @psalm-return array<int, int|ParameterType::*|Types::*|Type|null>
     */
    public function getTypes(): array
    {
        return $this->convertedTypes;
    }

    /**
     * @param list<mixed>          $values
     * @param Type|int|string|null $type
     * @psalm-param int|ParameterType::*|Types::*|Type|null $type
     */
    private function appendTypedParameter(array $values, $type): void
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
