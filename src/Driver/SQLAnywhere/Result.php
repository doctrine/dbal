<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;

use function sasql_fetch_assoc;
use function sasql_fetch_row;
use function sasql_stmt_affected_rows;
use function sasql_stmt_field_count;
use function sasql_stmt_reset;
use function sasql_stmt_result_metadata;

final class Result implements ResultInterface
{
    /** @var resource */
    private $statement;

    /** @var resource */
    private $result;

    /**
     * @param resource $statement
     */
    public function __construct($statement)
    {
        $this->statement = $statement;
        $this->result    = sasql_stmt_result_metadata($statement);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchNumeric()
    {
        return sasql_fetch_row($this->result);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAssociative()
    {
        return sasql_fetch_assoc($this->result);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchOne()
    {
        return FetchUtils::fetchOne($this);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    public function rowCount(): int
    {
        return sasql_stmt_affected_rows($this->statement);
    }

    public function columnCount(): int
    {
        return sasql_stmt_field_count($this->statement);
    }

    public function free(): void
    {
        sasql_stmt_reset($this->statement);
    }
}
