<?php

namespace Doctrine\DBAL\Driver;

use IteratorAggregate;
use ReturnTypeWillChange;

/**
 * @deprecated Use iterateNumeric(), iterateAssociative() or iterateColumn().
 */
class StatementIterator implements IteratorAggregate
{
    /** @var ResultStatement */
    private $statement;

    public function __construct(ResultStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * {@inheritdoc}
     */
    #[ReturnTypeWillChange]
    public function getIterator()
    {
        while (($result = $this->statement->fetch()) !== false) {
            yield $result;
        }
    }
}
