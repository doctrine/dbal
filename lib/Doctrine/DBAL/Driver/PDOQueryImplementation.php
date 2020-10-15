<?php

namespace Doctrine\DBAL\Driver;

use PDOStatement;

use function func_get_args;

use const PHP_VERSION_ID;

if (PHP_VERSION_ID >= 80000) {
    /**
     * @internal
     */
    trait PDOQueryImplementation
    {
        /**
         * @return PDOStatement
         */
        public function query(?string $query = null, ?int $fetchMode = null, mixed ...$fetchModeArgs)
        {
            return $this->doQuery($query, $fetchMode, ...$fetchModeArgs);
        }
    }
} else {
    /**
     * @internal
     */
    trait PDOQueryImplementation
    {
        /**
         * @return PDOStatement
         */
        public function query()
        {
            return $this->doQuery(...func_get_args());
        }
    }
}
