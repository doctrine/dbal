<?php

namespace Doctrine\DBAL\Driver;

use function func_get_args;

use const PHP_VERSION_ID;

if (PHP_VERSION_ID >= 80000) {
    /**
     * @internal
     */
    trait PDOStatementImplementations
    {
        /**
         * @deprecated Use one of the fetch- or iterate-related methods.
         *
         * @param int   $mode
         * @param mixed ...$args
         *
         * @return bool
         */
        public function setFetchMode($mode, ...$args)
        {
            return $this->doSetFetchMode($mode, ...$args);
        }

        /**
         * @deprecated Use fetchAllNumeric(), fetchAllAssociative() or fetchFirstColumn() instead.
         *
         * @param int|null $mode
         * @param mixed    ...$args
         *
         * @return mixed[]
         */
        public function fetchAll($mode = null, ...$args)
        {
            return $this->doFetchAll($mode, ...$args);
        }
    }
} else {
    /**
     * @internal
     */
    trait PDOStatementImplementations
    {
        /**
         * @deprecated Use one of the fetch- or iterate-related methods.
         *
         * @param int   $fetchMode
         * @param mixed $arg2
         * @param mixed $arg3
         */
        public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null): bool
        {
            return $this->doSetFetchMode(...func_get_args());
        }

        /**
         * @deprecated Use fetchAllNumeric(), fetchAllAssociative() or fetchFirstColumn() instead.
         *
         * @param int|null $fetchMode
         * @param mixed    $fetchArgument
         * @param mixed    $ctorArgs
         *
         * @return mixed[]
         */
        public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
        {
            return $this->doFetchAll(...func_get_args());
        }
    }
}
