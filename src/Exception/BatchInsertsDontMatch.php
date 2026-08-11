<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

final class BatchInsertsDontMatch extends INvalidARgumentException
{
    public static function new(): self
    {
        return new self('For batch inserts, all rows must have identical columns.');
    }
}
