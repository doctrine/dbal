<?php

namespace Doctrine\DBAL\Platforms;

/**
 * Provides features of the MySQL since 8.0.13 database platform.
 *
 * Note: Should not be used with versions prior to 8.0.13.
 *
 * This class will be merged with {@see MySQLPlatform} when support for MySQL
 * releases prior to 8.0.13 will be dropped.
 */
class MySQL8013Platform extends MySQL80Platform
{
}
