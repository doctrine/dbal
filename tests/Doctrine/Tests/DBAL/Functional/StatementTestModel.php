<?php

namespace Doctrine\Tests\DBAL\Functional;

class StatementTestModel
{
    public function __construct(string $x, string $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    /** @var int */
    public $a;

    /** @var int */
    public $b;

    /** @var string */
    public $x;

    /** @var string */
    public $y;
}
