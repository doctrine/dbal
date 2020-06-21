<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Abstraction\Result as AbstractionResult;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as BaseConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Result as DBALResult;
use PDO;

use const CASE_LOWER;
use const CASE_UPPER;

/**
 * Portability wrapper for a Connection.
 */
class Connection extends BaseConnection
{
    public const PORTABILITY_ALL           = 255;
    public const PORTABILITY_NONE          = 0;
    public const PORTABILITY_RTRIM         = 1;
    public const PORTABILITY_EMPTY_TO_NULL = 4;
    public const PORTABILITY_FIX_CASE      = 8;

    /** @var int */
    private $portability = self::PORTABILITY_NONE;

    /** @var int */
    private $case = 0;

    /** @var Converter */
    private $converter;

    /** {@inheritDoc} */
    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    ) {
        if (isset($params['portability'])) {
            $this->portability = $params['portability'];
        }

        if (isset($params['fetch_case'])) {
            $this->case = $params['fetch_case'];
        }

        unset($params['portability'], $params['fetch_case']);

        parent::__construct($params, $driver, $config, $eventManager);
    }

    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        parent::connect();

        $portability = (new OptimizeFlags())(
            $this->getDatabasePlatform(),
            $this->portability
        );

        $case = 0;

        if ($this->case !== 0 && ($portability & self::PORTABILITY_FIX_CASE) !== 0) {
            if ($this->_conn instanceof PDOConnection) {
                // make use of c-level support for case handling
                $this->_conn->getWrappedConnection()->setAttribute(PDO::ATTR_CASE, $this->case);
            } else {
                $case = $this->case === ColumnCase::LOWER ? CASE_LOWER : CASE_UPPER;
            }
        }

        $this->converter = new Converter(
            ($portability & self::PORTABILITY_EMPTY_TO_NULL) !== 0,
            ($portability & self::PORTABILITY_RTRIM) !== 0,
            $case
        );
    }

    /**
     * {@inheritdoc}
     */
    public function executeQuery(
        string $query,
        array $params = [],
        array $types = [],
        ?QueryCacheProfile $qcp = null
    ): AbstractionResult {
        return $this->wrapResult(
            parent::executeQuery($query, $params, $types, $qcp)
        );
    }

    /**
     * @return Statement
     */
    public function prepare(string $sql): DriverStatement
    {
        return new Statement(parent::prepare($sql), $this->converter);
    }

    public function query(string $sql): DriverResult
    {
        return $this->wrapResult(
            parent::query($sql)
        );
    }

    private function wrapResult(DriverResult $result): AbstractionResult
    {
        return new DBALResult(
            new Result($result, $this->converter),
            $this
        );
    }
}
