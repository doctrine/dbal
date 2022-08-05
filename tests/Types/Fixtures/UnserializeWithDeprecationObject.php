<?php

namespace Doctrine\DBAL\Tests\Types\Fixtures;

use function trigger_error;

use const E_USER_DEPRECATED;

class UnserializeWithDeprecationObject
{
    public function __wakeup(): void
    {
        trigger_error('Deprecation triggered', E_USER_DEPRECATED);
    }
}
