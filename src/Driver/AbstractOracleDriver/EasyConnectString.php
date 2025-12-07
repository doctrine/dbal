<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\AbstractOracleDriver;

use Doctrine\Deprecations\Deprecation;

use function assert;
use function implode;
use function is_array;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * Represents an Oracle Easy Connect string
 *
 * @link https://docs.oracle.com/database/121/NETAG/naming.htm
 */
final class EasyConnectString
{
    private function __construct(private readonly string $string)
    {
    }

    public function __toString(): string
    {
        return $this->string;
    }

    /**
     * Creates the object from an array representation
     *
     * @param mixed[] $params
     */
    public static function fromArray(array $params): self
    {
        return new self(self::renderParams($params));
    }

    /**
     * Creates the object from the given DBAL connection parameters.
     *
     * @param mixed[] $params
     */
    public static function fromConnectionParameters(array $params): self
    {
        if (isset($params['connectstring'])) {
            assert(is_string($params['connectstring']));

            return new self($params['connectstring']);
        }

        if (! isset($params['host'])) {
            $dbname = $params['dbname'] ?? '';
            assert(is_string($dbname));

            return new self($dbname);
        }

        $connectData = [];

        if (isset($params['service'])) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/7042',
                'Using the "service" parameter to indicate that the value of the "dbname" parameter is the'
                    . ' service name is deprecated. Use the "servicename" parameter instead.',
            );
        }

        if (isset($params['servicename']) || isset($params['dbname'])) {
            $serviceKey = 'SID';

            if (isset($params['service']) || isset($params['servicename'])) {
                $serviceKey = 'SERVICE_NAME';
            }

            $serviceName = $params['servicename'] ?? $params['dbname'];

            $connectData[$serviceKey] = $serviceName;
        }

        if (isset($params['instancename'])) {
            $connectData['INSTANCE_NAME'] = $params['instancename'];
        }

        if (! empty($params['pooled'])) {
            $connectData['SERVER'] = 'POOLED';
        }

        return self::fromArray([
            'DESCRIPTION' => [
                'ADDRESS' => [
                    'PROTOCOL' => $params['driverOptions']['protocol'] ?? 'TCP',
                    'HOST' => $params['host'],
                    'PORT' => $params['port'] ?? 1521,
                ],
                'CONNECT_DATA' => $connectData,
            ],
        ]);
    }

    /** @param mixed[] $params */
    private static function renderParams(array $params): string
    {
        $chunks = [];

        foreach ($params as $key => $value) {
            $string = self::renderValue($value);

            if ($string === '') {
                continue;
            }

            $chunks[] = sprintf('(%s=%s)', $key, $string);
        }

        return implode('', $chunks);
    }

    private static function renderValue(mixed $value): string
    {
        if (is_array($value)) {
            return self::renderParams($value);
        }

        assert(is_scalar($value));

        return (string) $value;
    }
}
