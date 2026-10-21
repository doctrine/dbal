<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Exception;

use Doctrine\DBAL\Driver\AbstractException;
use Throwable;

use function get_debug_type;
use function sprintf;

/** @internal */
final class ExtensionNotLoaded extends AbstractException
{
    public static function unsupportedConnection(mixed $connection): self
    {
        return new self(sprintf(
            'Cannot load SQLite extensions into a %s connection: use the sqlite3 driver,'
                . ' or the pdo_sqlite driver with PHP 8.4 or newer.',
            get_debug_type($connection),
        ));
    }

    public static function outsideOfExtensionDirectory(string $path, string $extensionDirectory): self
    {
        return new self(sprintf(
            'The sqlite3 driver can only load extensions located in the directory configured by the'
                . ' sqlite3.extension_dir php.ini setting ("%s"), which "%s" is not.',
            $extensionDirectory,
            $path,
        ));
    }

    public static function failed(string $path, ?Throwable $previous = null): self
    {
        return new self(sprintf('Could not load the SQLite extension "%s".', $path), null, 0, $previous);
    }
}
