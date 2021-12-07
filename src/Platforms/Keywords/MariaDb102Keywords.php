<?php

namespace Doctrine\DBAL\Platforms\Keywords;

/**
 * MariaDb reserved keywords list.
 *
 * @deprecated Use {@link MariaDBKeywords} instead.
 *
 * @link https://mariadb.com/kb/en/the-mariadb-library/reserved-words/
 */
final class MariaDb102Keywords extends MariaDBKeywords
{
    public function getName(): string
    {
        return 'MariaDb102';
    }
}
