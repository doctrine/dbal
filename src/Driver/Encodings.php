<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

final class Encodings
{
    /**
     * Represents whether a value should be bound with ASCII encoding
     *
     * @see \PDO::PARAM_STR_CHAR
     */
    public const ASCII = 536870912;

    /**
     * This class cannot be instantiated.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
}
