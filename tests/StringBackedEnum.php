<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

enum StringBackedEnum: string
{
    case FOO = 'foo';
    case BAR = 'bar';
}
