<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Initializer;

use Doctrine\DBAL\Driver\Mysqli\Exception\InvalidCharset;
use Doctrine\DBAL\Driver\Mysqli\Initializer;
use mysqli;
use mysqli_sql_exception;
use Override;

final readonly class Charset implements Initializer
{
    public function __construct(private string $charset)
    {
    }

    #[Override]
    public function initialize(mysqli $connection): void
    {
        try {
            $connection->set_charset($this->charset);
        } catch (mysqli_sql_exception $e) {
            throw InvalidCharset::upcast($e, $this->charset);
        }
    }
}
