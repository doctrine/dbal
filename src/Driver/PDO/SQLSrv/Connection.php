<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use Override;
use PDO;

final readonly class Connection extends AbstractConnectionMiddleware
{
    public function __construct(private PDOConnection $connection)
    {
        parent::__construct($connection);
    }

    #[Override]
    public function prepare(string $sql): Statement
    {
        return new Statement(
            $this->connection->prepare($sql),
        );
    }

    #[Override]
    public function getNativeConnection(): PDO
    {
        return $this->connection->getNativeConnection();
    }
}
