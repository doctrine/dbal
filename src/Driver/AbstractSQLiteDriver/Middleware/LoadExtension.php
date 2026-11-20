<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Exception\ExtensionNotLoaded;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Pdo\Sqlite;
use SensitiveParameter;
use SQLite3;
use Throwable;

use function dirname;
use function ini_get;
use function realpath;
use function str_contains;
use function strlen;
use function substr;

/**
 * Loads a SQLite extension into every new connection, e.g. SpatiaLite:
 *
 *     new LoadExtension('/usr/lib/x86_64-linux-gnu/mod_spatialite.so')
 *
 * The pdo_sqlite driver requires PHP 8.4 or newer. The sqlite3 driver can only load the extensions located in the
 * directory configured by the sqlite3.extension_dir php.ini setting: a file name is resolved in this directory.
 */
final class LoadExtension implements Middleware
{
    public function __construct(private readonly string $path)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new class ($driver, $this->path) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly string $path)
            {
                parent::__construct($driver);
            }

            /**
             * {@inheritDoc}
             */
            public function connect(
                #[SensitiveParameter]
                array $params,
            ): Connection {
                $connection = parent::connect($params);
                $native     = $connection->getNativeConnection();

                try {
                    if ($native instanceof SQLite3) {
                        $loaded = $native->loadExtension($this->getExtensionDirectoryRelativePath());
                    } elseif ($native instanceof Sqlite) {
                        $native->loadExtension($this->path);
                        $loaded = true;
                    } else {
                        throw ExtensionNotLoaded::unsupportedConnection($native);
                    }
                } catch (ExtensionNotLoaded $e) {
                    throw $e;
                } catch (Throwable $e) {
                    throw ExtensionNotLoaded::failed($this->path, $e);
                }

                if (! $loaded) {
                    throw ExtensionNotLoaded::failed($this->path);
                }

                return $connection;
            }

            /** @throws ExtensionNotLoaded */
            private function getExtensionDirectoryRelativePath(): string
            {
                if (! str_contains($this->path, '/')) {
                    return $this->path;
                }

                $directory = ini_get('sqlite3.extension_dir');
                $realPath  = realpath($this->path);

                if ($directory === false || $directory === '' || $realPath === false) {
                    throw ExtensionNotLoaded::outsideOfExtensionDirectory($this->path, (string) $directory);
                }

                $realDirectory = realpath($directory);

                if ($realDirectory === false || dirname($realPath) !== $realDirectory) {
                    throw ExtensionNotLoaded::outsideOfExtensionDirectory($this->path, $directory);
                }

                return substr($realPath, strlen($realDirectory) + 1);
            }
        };
    }
}
