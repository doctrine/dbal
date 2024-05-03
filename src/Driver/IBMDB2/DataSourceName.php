<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use SensitiveParameter;

use function implode;
use function sprintf;
use function str_contains;

/**
 * IBM DB2 DSN
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
            $chunks[] = sprintf('%s=%s', $key, $value);
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
        if (isset($params['dbname']) && str_contains($params['dbname'], '=')) {
            return new self($params['dbname']);
        }

        $dsnParams = [];
        $serverIsIBMi = str_contains(php_uname(), 'OS400');

        foreach (
            $serverIsIBMi ? [
                'dbname'   => 'DSN',
                'user'     => 'UID',
                'password' => 'PWD',
            ] : [
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
