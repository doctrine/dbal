<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Initializer;

use Doctrine\DBAL\Driver\Mysqli\Initializer;
use mysqli;
use SensitiveParameter;

final class Secure implements Initializer
{
    public function __construct(
        #[SensitiveParameter]
        private readonly string $key,
        private readonly string $cert,
        private readonly string $ca,
        private readonly string $capath,
        private readonly string $cipher,
    ) {
    }

    public function initialize(mysqli $connection): void
    {
        $connection->ssl_set($this->key, $this->cert, $this->ca, $this->capath, $this->cipher);
    }
}
