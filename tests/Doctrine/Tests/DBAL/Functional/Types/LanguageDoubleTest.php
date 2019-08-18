<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Types;

use const LC_ALL;
use function setlocale;

class LanguageDoubleTest extends DoubleTest
{
    protected function setUp() : void
    {
        setlocale(LC_ALL, 'de_DE.UTF-8', 'de_DE', 'de', 'ge');
        parent::setUp();
    }
}
