<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\ParameterType;

/**
 * Driver-level statement
 */
interface Statement
{
    /**
     * Binds a value to a corresponding named (not supported by mysqli driver, see comment below) or positional
     * placeholder in the SQL statement that was used to prepare the statement.
     *
     * As mentioned above, the named parameters are not natively supported by the mysqli driver, use executeQuery(),
     * fetchAll(), fetchArray(), fetchColumn(), fetchAssoc() methods to have the named parameter emulated by doctrine.
     *
     * @param int|string    $param Parameter identifier. For a prepared statement using named placeholders,
     *                             this will be a parameter name of the form :name. For a prepared statement
     *                             using question mark placeholders, this will be the 1-indexed position
     *                             of the parameter.
     * @param mixed         $value The value to bind to the parameter.
     * @param ParameterType $type  Explicit data type for the parameter using the {@see ParameterType}
     *                             constants.
     *
     * @throws Exception
     */
    public function bindValue(int|string $param, mixed $value, ParameterType $type): void;

    /**
     * Executes a prepared statement
     *
     * @throws Exception
     */
    public function execute(): Result;
}
