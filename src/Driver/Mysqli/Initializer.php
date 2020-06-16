<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use mysqli;

interface Initializer
{
    /**
     * @throws MysqliException
     */
    public function initialize(mysqli $connection): void;
}
