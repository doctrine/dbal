<?php
declare(strict_types=1);

namespace Doctrine\Tests\Object;

class SimpleObject
{
    private $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function getString(): string
    {
        return $this->string;
    }
}
