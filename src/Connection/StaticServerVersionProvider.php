<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Connection;

use Doctrine\DBAL\ServerVersionProvider;

class StaticServerVersionProvider implements ServerVersionProvider
{
    private string $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    public function getServerVersion(): string
    {
        return $this->version;
    }
}
