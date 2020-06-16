<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Initializer;

use Doctrine\DBAL\Driver\Mysqli\Initializer;
use mysqli;

final class Secure implements Initializer
{
    /** @var string|null */
    private $key;

    /** @var string|null */
    private $cert;

    /** @var string|null */
    private $ca;

    /** @var string|null */
    private $capath;

    /** @var string|null */
    private $cipher;

    public function __construct(?string $key, ?string $cert, ?string $ca, ?string $capath, ?string $cipher)
    {
        $this->key    = $key;
        $this->cert   = $cert;
        $this->ca     = $ca;
        $this->capath = $capath;
        $this->cipher = $cipher;
    }

    public function initialize(mysqli $connection): void
    {
        $connection->ssl_set($this->key, $this->cert, $this->ca, $this->capath, $this->cipher);
    }
}
