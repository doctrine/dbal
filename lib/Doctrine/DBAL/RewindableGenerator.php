<?php

namespace Doctrine\DBAL;

class RewindableGenerator implements \IteratorAggregate
{
    private $generator;

    /**
     * @param callable $generator
     */
    public function __construct(callable $generator)
    {
        $this->generator = $generator;
    }

    public function getIterator()
    {
        return ($this->generator)();
    }
}
