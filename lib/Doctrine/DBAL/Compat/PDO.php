<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

/**
 * PDO shim to keep DBAL compatible with non-PDO PHP installations.
 *
 * @link http://php.net/manual/en/pdo.constants.php
 * @package Doctrine\DBAL
 */
class PDO
{

    /**
     * Represents a boolean data type.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-bool
     */
    const PARAM_BOOL = 5;

    /**
     * Represents the SQL NULL data type.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-null
     */
    const PARAM_NULL = 0;

    /**
     * Represents the SQL INTEGER data type.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-int
     */
    const PARAM_INT = 1;

    /**
     * Represents the SQL CHAR, VARCHAR, or other string data type.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-str
     */
    const PARAM_STR = 2;

    /**
     * Represents the SQL large object data type.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-lob
     */
    const PARAM_LOB = 3;

    /**
     * Represents a recordset type.  Not currently supported by any drivers.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-stmt
     */
    const PARAM_STMT = 4;

    /**
     * Specifies that the parameter is an INOUT parameter for a stored
     * procedure. You must bitwise-OR this value with an explicit
     * PDO::PARAM_* data type.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-input-output
     */
    const PARAM_INPUT_OUTPUT = 2147483648;

    /**
     * Specifies that the fetch method shall return each row as an object with
     * variable names that correspond to the column names returned in the result
     * set. PDO::FETCH_LAZY creates the object variable names as they are accessed.
     * Not valid inside PDOStatement::fetchAll().
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-lazy
     */
    const FETCH_LAZY = 1;

    /**
     * Specifies that the fetch method shall return each row as an array indexed
     * by column name as returned in the corresponding result set. If the result
     * set contains multiple columns with the same name,
     * PDO::FETCH_ASSOC returns
     * only a single value per column name.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-assoc
     */
    const FETCH_ASSOC = 2;

    /**
     * Specifies that the fetch method shall return each row as an array indexed
     * by column name as returned in the corresponding result set. If the result
     * set contains multiple columns with the same name,
     * PDO::FETCH_NAMED returns
     * an array of values per column name.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-named
     */
    const FETCH_NAMED = 11;

    /**
     * Specifies that the fetch method shall return each row as an array indexed
     * by column number as returned in the corresponding result set, starting at
     * column 0.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-num
     */
    const FETCH_NUM = 3;

    /**
     * Specifies that the fetch method shall return each row as an array indexed
     * by both column name and number as returned in the corresponding result set,
     * starting at column 0.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-both
     */
    const FETCH_BOTH = 4;

    /**
     * Specifies that the fetch method shall return each row as an object with
     * property names that correspond to the column names returned in the result
     * set.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-obj
     */
    const FETCH_OBJ = 5;

    /**
     * Specifies that the fetch method shall return TRUE and assign the values of
     * the columns in the result set to the PHP variables to which they were
     * bound with the PDOStatement::bindParam() or
     * PDOStatement::bindColumn() methods.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-bound
     */
    const FETCH_BOUND = 6;

    /**
     * Specifies that the fetch method shall return only a single requested
     * column from the next row in the result set.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-column
     */
    const FETCH_COLUMN = 7;

    /**
     * Specifies that the fetch method shall return a new instance of the
     * requested class, mapping the columns to named properties in the class.
     * Note:
     * The magic
     * __set()
     * method is called if the property doesn't exist in the requested class
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-class
     */
    const FETCH_CLASS = 8;

    /**
     * Specifies that the fetch method shall update an existing instance of the
     * requested class, mapping the columns to named properties in the class.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-into
     */
    const FETCH_INTO = 9;

    /**
     * Allows completely customize the way data is treated on the fly (only
     * valid inside PDOStatement::fetchAll()).
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-func
     */
    const FETCH_FUNC = 10;

    /**
     * Group return by values. Usually combined with
     * PDO::FETCH_COLUMN or
     * PDO::FETCH_KEY_PAIR.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-group
     */
    const FETCH_GROUP = 65536;

    /**
     * Fetch only the unique values.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-unique
     */
    const FETCH_UNIQUE = 196608;

    /**
     * Fetch a two-column result into an array where the first column is a key and the second column
     * is the value. Available since PHP 5.2.3.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-key-pair
     */
    const FETCH_KEY_PAIR = 12;

    /**
     * Determine the class name from the value of first column.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-classtype
     */
    const FETCH_CLASSTYPE = 262144;

    /**
     * As PDO::FETCH_INTO but object is provided as a serialized string.
     * Available since PHP 5.1.0. Since PHP 5.3.0 the class constructor is never called if this
     * flag is set.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-serialize
     */
    const FETCH_SERIALIZE = 524288;

    /**
     * Call the constructor before setting properties. Available since PHP 5.2.0.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-props-late
     */
    const FETCH_PROPS_LATE = 1048576;

    /**
     * If this value is FALSE, PDO attempts to disable autocommit so that the
     * connection begins a transaction.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-autocommit
     */
    const ATTR_AUTOCOMMIT = 0;

    /**
     * Setting the prefetch size allows you to balance speed against memory
     * usage for your application.  Not all database/driver combinations support
     * setting of the prefetch size.  A larger prefetch size results in
     * increased performance at the cost of higher memory usage.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-prefetch
     */
    const ATTR_PREFETCH = 1;

    /**
     * Sets the timeout value in seconds for communications with the database.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-timeout
     */
    const ATTR_TIMEOUT = 2;

    /**
     * See the Errors and error
     * handling section for more information about this attribute.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-errmode
     */
    const ATTR_ERRMODE = 3;

    /**
     * This is a read only attribute; it will return information about the
     * version of the database server to which PDO is connected.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-server-version
     */
    const ATTR_SERVER_VERSION = 4;

    /**
     * This is a read only attribute; it will return information about the
     * version of the client libraries that the PDO driver is using.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-client-version
     */
    const ATTR_CLIENT_VERSION = 5;

    /**
     * This is a read only attribute; it will return some meta information about the
     * database server to which PDO is connected.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-server-info
     */
    const ATTR_SERVER_INFO = 6;

    /**
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-connection-status
     */
    const ATTR_CONNECTION_STATUS = 7;

    /**
     * Force column names to a specific case specified by the PDO::CASE_*
     * constants.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-case
     */
    const ATTR_CASE = 8;

    /**
     * Get or set the name to use for a cursor.  Most useful when using
     * scrollable cursors and positioned updates.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-cursor-name
     */
    const ATTR_CURSOR_NAME = 9;

    /**
     * Selects the cursor type.  PDO currently supports either
     * PDO::CURSOR_FWDONLY and
     * PDO::CURSOR_SCROLL. Stick with
     * PDO::CURSOR_FWDONLY unless you know that you need a
     * scrollable cursor.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-cursor
     */
    const ATTR_CURSOR = 10;

    /**
     * Returns the name of the driver.
     * Example #1 using PDO::ATTR_DRIVER_NAME
     * <code>
     *     if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
     *         echo "Running on mysql; doing something mysql specific here\n";
     *     }
     * </code>
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-driver-name
     */
    const ATTR_DRIVER_NAME = 16;

    /**
     * Convert empty strings to SQL NULL values on data fetches.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-oracle-nulls
     */
    const ATTR_ORACLE_NULLS = 11;

    /**
     * Request a persistent connection, rather than creating a new connection.
     * See Connections and Connection
     * management for more information on this attribute.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-persistent
     */
    const ATTR_PERSISTENT = 12;

    /**
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-statement-class
     */
    const ATTR_STATEMENT_CLASS = 13;

    /**
     * Prepend the containing catalog name to each column name returned in the
     * result set. The catalog name and column name are separated by a decimal
     * (.) character.  Support of this attribute is at the driver level; it may
     * not be supported by your driver.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-fetch-catalog-names
     */
    const ATTR_FETCH_CATALOG_NAMES = 15;

    /**
     * Prepend the containing table name to each column name returned in the
     * result set. The table name and column name are separated by a decimal (.)
     * character. Support of this attribute is at the driver level; it may not
     * be supported by your driver.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-fetch-table-names
     */
    const ATTR_FETCH_TABLE_NAMES = 14;

    /**
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-stringify-fetches
     */
    const ATTR_STRINGIFY_FETCHES = 17;

    /**
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-max-column-len
     */
    const ATTR_MAX_COLUMN_LEN = 18;

    /**
     * Available since PHP 5.2.0
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-default-fetch-mode
     */
    const ATTR_DEFAULT_FETCH_MODE = 19;

    /**
     * Available since PHP 5.1.3.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.attr-emulate-prepares
     */
    const ATTR_EMULATE_PREPARES = 20;

    /**
     * Do not raise an error or exception if an error occurs. The developer is
     * expected to explicitly check for errors.  This is the default mode.
     * See Errors and error handling
     * for more information about this attribute.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.errmode-silent
     */
    const ERRMODE_SILENT = 0;

    /**
     * Issue a PHP E_WARNING message if an error occurs.
     * See Errors and error handling
     * for more information about this attribute.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.errmode-warning
     */
    const ERRMODE_WARNING = 1;

    /**
     * Throw a PDOException if an error occurs.
     * See Errors and error handling
     * for more information about this attribute.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.errmode-exception
     */
    const ERRMODE_EXCEPTION = 2;

    /**
     * Leave column names as returned by the database driver.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.case-natural
     */
    const CASE_NATURAL = 0;

    /**
     * Force column names to lower case.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.case-lower
     */
    const CASE_LOWER = 2;

    /**
     * Force column names to upper case.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.case-upper
     */
    const CASE_UPPER = 1;

    /**
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.null-natural
     */
    const NULL_NATURAL = 0;

    /**
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.null-empty-string
     */
    const NULL_EMPTY_STRING = 1;

    /**
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.null-to-string
     */
    const NULL_TO_STRING = 2;

    /**
     * Fetch the next row in the result set. Valid only for scrollable cursors.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-ori-next
     */
    const FETCH_ORI_NEXT = 0;

    /**
     * Fetch the previous row in the result set. Valid only for scrollable
     * cursors.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-ori-prior
     */
    const FETCH_ORI_PRIOR = 1;

    /**
     * Fetch the first row in the result set. Valid only for scrollable cursors.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-ori-first
     */
    const FETCH_ORI_FIRST = 2;

    /**
     * Fetch the last row in the result set. Valid only for scrollable cursors.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-ori-last
     */
    const FETCH_ORI_LAST = 3;

    /**
     * Fetch the requested row by row number from the result set. Valid only
     * for scrollable cursors.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-ori-abs
     */
    const FETCH_ORI_ABS = 4;

    /**
     * Fetch the requested row by relative position from the current position
     * of the cursor in the result set. Valid only for scrollable cursors.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.fetch-ori-rel
     */
    const FETCH_ORI_REL = 5;

    /**
     * Create a PDOStatement object with a forward-only cursor.  This is the
     * default cursor choice, as it is the fastest and most common data access
     * pattern in PHP.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.cursor-fwdonly
     */
    const CURSOR_FWDONLY = 0;

    /**
     * Create a PDOStatement object with a scrollable cursor. Pass the
     * PDO::FETCH_ORI_* constants to control the rows fetched from the result set.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.cursor-scroll
     */
    const CURSOR_SCROLL = 1;

    /**
     * Corresponds to SQLSTATE '00000', meaning that the SQL statement was
     * successfully issued with no errors or warnings.  This constant is for
     * your convenience when checking PDO::errorCode() or
     * PDOStatement::errorCode() to determine if an error
     * occurred.  You will usually know if this is the case by examining the
     * return code from the method that raised the error condition anyway.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.err-none
     */
    const ERR_NONE = 00000;

    /**
     * Allocation event
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-evt-alloc
     */
    const PARAM_EVT_ALLOC = 0;

    /**
     * Deallocation event
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-evt-free
     */
    const PARAM_EVT_FREE = 1;

    /**
     * Event triggered prior to execution of a prepared statement.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-evt-exec-pre
     */
    const PARAM_EVT_EXEC_PRE = 2;

    /**
     * Event triggered subsequent to execution of a prepared statement.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-evt-exec-post
     */
    const PARAM_EVT_EXEC_POST = 3;

    /**
     * Event triggered prior to fetching a result from a resultset.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-evt-fetch-pre
     */
    const PARAM_EVT_FETCH_PRE = 4;

    /**
     * Event triggered subsequent to fetching a result from a resultset.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-evt-fetch-post
     */
    const PARAM_EVT_FETCH_POST = 5;

    /**
     * Event triggered during bound parameter registration
     * allowing the driver to normalize the parameter name.
     *
     * @link http://php.net/manual/en/pdo.constants.php#pdo.constants.param-evt-normalize
     */
    const PARAM_EVT_NORMALIZE = 6;

    public function __construct()
    {
        throw new \LogicException('Your PHP Installation has no PDO support, you cannot use PDO-based drivers.');
    }

}