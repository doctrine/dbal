<?php

namespace Doctrine\DBAL\Schema;

final class Expression
{
    /** @var string $value */
    private $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return $this->value;
    }
}
