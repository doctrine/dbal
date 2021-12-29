<?php

namespace Doctrine\DBAL\Tests\Tools\TestAsset;

abstract class ParentClass implements TestInterface
{
    /** @var int */
    public $parentPublicAttribute = 1;

    /** @var int */
    protected $parentProtectedAttribute = 2;

    /** @var int */
    private $parentPrivateAttribute = 3;
}
