<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\SQL\Parser\Visitor;
use Override;

use function count;
use function implode;

/**
 * Converts positional (?) into named placeholders (:param<num>).
 *
 * Oracle does not support positional parameters, hence this method converts all
 * positional parameters into artificially named parameters.
 *
 * @internal This class is not covered by the backward compatibility promise
 */
final class ConvertPositionalToNamedPlaceholders implements Visitor
{
    /** @var list<string> */
    private array $buffer = [];

    /** @var array<int,string> */
    private array $parameterMap = [];

    #[Override]
    public function acceptOther(string $sql): void
    {
        $this->buffer[] = $sql;
    }

    #[Override]
    public function acceptPositionalParameter(string $sql): void
    {
        $position = count($this->parameterMap) + 1;
        $param    = ':param' . $position;

        $this->parameterMap[$position] = $param;

        $this->buffer[] = $param;
    }

    #[Override]
    public function acceptNamedParameter(string $sql): void
    {
        $this->buffer[] = $sql;
    }

    public function getSQL(): string
    {
        return implode('', $this->buffer);
    }

    /** @return array<int,string> */
    public function getParameterMap(): array
    {
        return $this->parameterMap;
    }
}
