<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLSrv;

/**
 * Class StringCastableObject
 * @package Doctrine\Tests\DBAL\Functional\Driver\SQLSrv
 *
 * simple object to test automatic string casts
 */
class StringCastableObject
{
    /**
     * @var string
     */
    private $string;

    public function __construct($string)
    {
        $this->string = $string;
    }

    public function __toString()
    {
        return $this->string;
    }
}
