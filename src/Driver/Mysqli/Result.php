<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Mysqli\Exception\StatementError;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use mysqli_sql_exception;
use mysqli_stmt;

use function array_column;
use function array_combine;
use function array_fill;
use function count;

final class Result implements ResultInterface
{
    /**
     * Whether the statement result has columns. The property should be used only after the result metadata
     * has been fetched ({@see $metadataFetched}). Otherwise, the property value is undetermined.
     */
    private readonly bool $hasColumns;

    /**
     * Mapping of statement result column indexes to their names. The property should be used only
     * if the statement result has columns ({@see $hasColumns}). Otherwise, the property value is undetermined.
     *
     * @var array<int,string>
     */
    private readonly array $columnNames;

    /** @var mixed[] */
    private array $boundValues = [];

    /**
     * @internal The result can be only instantiated by its driver connection or statement.
     *
     * @throws Exception
     */
    public function __construct(private readonly mysqli_stmt $statement)
    {
        $meta              = $statement->result_metadata();
        $this->hasColumns  = $meta !== false;
        $this->columnNames = $meta !== false ? array_column($meta->fetch_fields(), 'name') : [];

        if ($meta === false) {
            return;
        }

        $meta->free();

        // Store result of every execution which has it. Otherwise it will be impossible
        // to execute a new statement in case if the previous one has non-fetched rows
        // @link http://dev.mysql.com/doc/refman/5.7/en/commands-out-of-sync.html
        $this->statement->store_result();

        // Bind row values _after_ storing the result. Otherwise, if mysqli is compiled with libmysql,
        // it will have to allocate as much memory as it may be needed for the given column type
        // (e.g. for a LONGBLOB column it's 4 gigabytes)
        // @link https://bugs.php.net/bug.php?id=51386#1270673122
        //
        // Make sure that the values are bound after each execution. Otherwise, if free() has been
        // previously called on the result, the values are unbound making the statement unusable.
        //
        // It's also important that row values are bound after _each_ call to store_result(). Otherwise,
        // if mysqli is compiled with libmysql, subsequently fetched string values will get truncated
        // to the length of the ones fetched during the previous execution.
        $this->boundValues = array_fill(0, count($this->columnNames), null);

        // The following is necessary as PHP cannot handle references to properties properly
        $refs = &$this->boundValues;

        if (! $this->statement->bind_result(...$refs)) {
            throw StatementError::new($this->statement);
        }
    }

    public function fetchNumeric(): array|false
    {
        try {
            $ret = $this->statement->fetch();
        } catch (mysqli_sql_exception $e) {
            throw StatementError::upcast($e);
        }

        if ($ret === false) {
            throw StatementError::new($this->statement);
        }

        if ($ret === null) {
            return false;
        }

        $values = [];

        foreach ($this->boundValues as $v) {
            $values[] = $v;
        }

        return $values;
    }

    public function fetchAssociative(): array|false
    {
        $values = $this->fetchNumeric();

        if ($values === false) {
            return false;
        }

        return array_combine($this->columnNames, $values);
    }

    public function fetchOne(): mixed
    {
        return FetchUtils::fetchOne($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    public function rowCount(): int|string
    {
        if ($this->hasColumns) {
            return $this->statement->num_rows;
        }

        if (0 > $this->statement->affected_rows) {
            // Uncomment when find the way to reproduce the case.
            // throw StatementError::new($this->statement);
        }

        return $this->statement->affected_rows;
    }

    public function columnCount(): int
    {
        return $this->statement->field_count;
    }

    public function free(): void
    {
        $this->statement->free_result();
    }
}
