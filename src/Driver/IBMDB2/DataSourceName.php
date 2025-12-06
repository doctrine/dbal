<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use SensitiveParameter;

use function implode;
use function sprintf;
use function str_contains;

/**
 * Db2 DSN
 */
final class DataSourceName
{
    private function __construct(
        #[SensitiveParameter]
        private readonly string $string,
    ) {
    }

    public function toString(): string
    {
        return $this->string;
    }

    /**
     * Creates the object from an array representation
     *
     * @param array<string,mixed> $params
     */
    public static function fromArray(
        #[SensitiveParameter]
        array $params,
    ): self {
        $chunks = [];

        foreach ($params as $key => $value) {
            assert(is_scalar($value)); // Since value is mixed, ensure it's scalar for string conversion
            $chunks[] = sprintf('%s=%s', $key, (string) $value);
        }

        return new self(implode(';', $chunks));
    }

    /**
     * Creates the object from the given DBAL connection parameters.
     *
     * @param array<string,mixed> $params
     */
    public static function fromConnectionParameters(#[SensitiveParameter]
    array $params,): self
    {
        if (isset($params['dbname'])) {
            assert(is_string($params['dbname'])); // Check type to help phpstan infer the type before using str_contains
            if (str_contains($params['dbname'], '=')) {
                return new self($params['dbname']);
            }
        }

        $dsnParams = [];

        foreach (
            [
                'host'     => 'HOSTNAME',
                'port'     => 'PORT',
                'protocol' => 'PROTOCOL',
                'dbname'   => 'DATABASE',
                'user'     => 'UID',
                'password' => 'PWD',
            ] as $dbalParam => $dsnParam
        ) {
            if (! isset($params[$dbalParam])) {
                continue;
            }

            $dsnParams[$dsnParam] = $params[$dbalParam];
        }

        return self::fromArray($dsnParams);
    }
}
