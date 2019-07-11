<?php

namespace Doctrine\DBAL\Schema\Exceptions;

class Expression
{
    /** @var mixed $value */
    protected $value;

    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    public function __toString() : string
    {
        return (string) $this->getValue();
    }
}
