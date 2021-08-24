<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

interface ServerVersionProvider
{
    /**
     * Returns the database server version
     */
    public function getServerVersion(): string;
}
