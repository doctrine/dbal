<?php

namespace Doctrine\DBAL\Driver;

class StatementIterator implements \IteratorAggregate
{
    /**
     * @var Statement
     */
    private $statement;

    /**
     * @param Statement $statement
     */
    public function __construct(Statement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        while (false !== ($result = $this->statement->fetch())) {
            yield $result;
        }
    }
}
