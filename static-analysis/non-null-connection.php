<?php

declare(strict_types=1);

namespace Doctrine\StaticAnalysis\DBAL;

use Doctrine\DBAL\Connection;

class WrappingConnection extends Connection
{
    public function usesDriverConnection(): void
    {
        if (! $this->isConnected()) {
            return;
        }

        $this->_conn->exec('DROP TABLE students;');
    }
}
