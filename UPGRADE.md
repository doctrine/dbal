# Upgrade to 4.0

## BC BREAK: Removed `ExpressionBuilder` methods

The `andX()` and `orX()` methods of the `ExpressionBuilder` class have been removed. Use `and()` and `or()` instead.

## BC BREAK: Removed `CompositeExpression` methods

The `add()` and `addMultiple()` methods of the `CompositeExpression` class have been removed. Use `with()` instead, which returns a new instance.
The `CompositeExpression` class is now immutable.

## BC BREAK: Changes in the QueryBuilder API.

1. The `select()`, `addSelect()`, `groupBy()` and `addGroupBy()` methods no longer accept an array of arguments. Pass each expression as an individual argument or expand an array of expressions using the `...` operator.
2. The `select()`, `addSelect()`, `groupBy()` and `addGroupBy()` methods no longer ignore the first argument if it's empty.
3. The `addSelect()` method can be no longer called without arguments.
4. The `insert()`, `update()` and `delete()` methods now require the `$table` parameter, and do not support aliases anymore.
5. The `add()`, `getQueryPart()`, `getQueryParts()`, `resetQueryPart()` and `resetQueryParts()` methods are removed.
6. For a `select()` query, the `getSQL()` method now throws an expression if no `SELECT` expressions have been provided.

## BC BREAK: `OCI8Statement::convertPositionalToNamedPlaceholders()` is removed.

The `OCI8Statement::convertPositionalToNamedPlaceholders()` method has been extracted to an internal utility class.

## BC BREAK: `ServerInfoAwareConnection::requiresQueryForServerVersion()` is removed.

The `ServerInfoAwareConnection::requiresQueryForServerVersion()` method has been removed as an implementation detail which is the same for almost all supported drivers.

## BC BREAK: Changes in obtaining the currently selected database name

- The `Doctrine\DBAL\Driver::getDatabase()` method has been removed. Please use `Doctrine\DBAL\Connection::getDatabase()` instead.
- `Doctrine\DBAL\Connection::getDatabase()` will always return the name of the database currently connected to, regardless of the configuration parameters and will initialize a database connection if it's not yet established.
- A call to `Doctrine\DBAL\Connection::getDatabase()`, when connected to an SQLite database, will no longer return the database file path.

## BC BREAK: Changes in handling string and binary columns

- When generating schema DDL, DBAL no longer provides the default length for string and binary columns. The application may need to provide the column length if required by the target platform.
- The `\DBAL\Platforms\AbstractPlatform::getVarcharTypeDeclarationSQL()` method has been renamed to `::getStringTypeDeclarationSQL()`.
- The following `AbstractPlatform` methods have been removed as no longer relevant: `::getCharMaxLength()`, `::getVarcharMaxLength()`, `::getVarcharDefaultLength()`, `::getBinaryMaxLength()`, `::getBinaryDefaultLength()`. 

## BC BREAK: Changes in `Doctrine\DBAL\Event\SchemaCreateTableEventArgs`

Table columns are no longer indexed by column name. Use the `name` attribute of the column instead.

## BC BREAK: Classes made final

- Class constant `SQLSrvStatement::LAST_INSERT_ID_SQL` was changed from public to private.
- Class `Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser` was made final.
- Class `Doctrine\DBAL\Sharding\SQLAzure\Schema\MultiTenantVisitor` was made final.
- Class `Doctrine\DBAL\Sharding\SQLAzure\SQLAzureFederationsSynchronizer` was made final.
- Class `Doctrine\DBAL\Sharding\PoolingShardManager` was made final.
- Class `Doctrine\DBAL\Id\TableGeneratorSchemaVisitor` was made final.
- Class `Doctrine\DBAL\Driver\Mysqli\Driver` was made final.
- Class `Doctrine\DBAL\Driver\Mysqli\MysqliStatement` was made final.
- Class `Doctrine\DBAL\Driver\OCI8\Driver` was made final.
- Class `Doctrine\DBAL\Driver\OCI8\OCI8Connection` was made final.
- Class `Doctrine\DBAL\Driver\OCI8\OCI8Statement` was made final.
- Class `Doctrine\DBAL\Driver\PDOSqlsrv\Driver` was made final.
- Class `Doctrine\DBAL\Driver\PDOSqlsrv\Statement` was made final.
- Class `Doctrine\DBAL\Driver\PDOMySql\Driver` was made final.
- Class `Doctrine\DBAL\Driver\IBMDB2\DB2Connection` was made final.
- Class `Doctrine\DBAL\Driver\IBMDB2\DB2Statement` was made final.
- Class `Doctrine\DBAL\Driver\IBMDB2\DB2Driver` was made final.
- Class `Doctrine\DBAL\Driver\SQLSrv\SQLSrvStatement` was made final.
- Class `Doctrine\DBAL\Driver\SQLSrv\Driver` was made final.
- Class `Doctrine\DBAL\Driver\SQLSrv\SQLSrvConnection` was made final.
- Class `Doctrine\DBAL\Driver\SQLAnywhere\SQLAnywhereConnection` was made final.
- Class `Doctrine\DBAL\Driver\SQLAnywhere\Driver` was made final.
- Class `Doctrine\DBAL\Driver\SQLAnywhere\SQLAnywhereStatement` was made final.
- Class `Doctrine\DBAL\Driver\PDOPgSql\Driver` was made final.
- Class `Doctrine\DBAL\Driver\PDOOracle\Driver` was made final.
- Class `Doctrine\DBAL\Driver\PDOSqlite\Driver` was made final.
- Class `Doctrine\DBAL\Driver\StatementIterator` was made final.
- Class `Doctrine\DBAL\Cache\ResultCacheStatement` was made final.
- Class `Doctrine\DBAL\Cache\ArrayStatement` was made final.
- Class `Doctrine\DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer` was made final.
- Class `Doctrine\DBAL\Schema\Visitor\RemoveNamespacedAssets` was made final.
- Class `Doctrine\DBAL\Portability\Statement` was made final.

## BC BREAK: Changes in the `Doctrine\DBAL\Schema` API

- Column precision no longer defaults to 10. The default value is NULL.
- Asset names are no longer nullable. An empty asset name should be represented as an empty string.
- `Doctrine\DBAL\Schema\AbstractSchemaManager::_getPortableTriggersList()` and `::_getPortableTriggerDefinition()` have been removed.

## BC BREAK: Changes in the `Doctrine\DBAL\Event` API

- `SchemaAlterTableAddColumnEventArgs::addSql()` and the same method in other `SchemaEventArgs`-based classes no longer accept an array of SQL statements. They accept a variadic string.
- `ConnectionEventArgs::getDriver()`, `::getDatabasePlatform()` and `::getSchemaManager()` methods have been removed. The connection information can be obtained from the connection which is available via `::getConnection()`.
- `SchemaColumnDefinitionEventArgs::getDatabasePlatform()` and `SchemaIndexDefinitionEventArgs::getDatabasePlatform()` have been removed for the same reason as above.

## BC BREAK: Changes in the `Doctrine\DBAL\Connection` API

- The following methods have been removed as leaking internal implementation details: `::getHost()`, `::getPort()`, `::getUsername()`, `::getPassword()`.
- The `::getDatabase()` method can now return null which means that no database is currently selected.

## BC BREAK: Changes in `Doctrine\DBAL\Driver\SQLSrv\LastInsertId`

- The class stores the last inserted ID as a nullable string, not an integer, which is reflected in the method signatures.

## BC BREAK: Changes in the `Doctrine\DBAL\Schema` API

- Method `Doctrine\DBAL\Schema\AbstractSchemaManager::_getPortableViewDefinition()` no longer optionally returns false. It will always return a `Doctrine\DBAL\Schema\View` instance.
- Method `Doctrine\DBAL\Schema\Comparator::diffTable()` now optionally returns null instead of false.
- Property `Doctrine\DBAL\Schema\Table::$_primaryKeyName` is now optionally null instead of false.
- Property `Doctrine\DBAL\Schema\TableDiff::$newName` is now optionally null instead of false.
- Method `Doctrine\DBAL\Schema\AbstractSchemaManager::tablesExist()` no longer accepts a string. Use `Doctrine\DBAL\Schema\AbstractSchemaManager::tableExists()` instead.
- Method `Doctrine\DBAL\Schema\OracleSchemaManager::createDatabase()` no longer accepts `null` for `$database` argument.
- Removed unused method `Doctrine\DBAL\Schema\AbstractSchemaManager::_getPortableFunctionsList()`
- Removed unused method `Doctrine\DBAL\Schema\AbstractSchemaManager::_getPortableFunctionDefinition()`
- Removed unused method `Doctrine\DBAL\Schema\OracleSchemaManager::_getPortableFunctionDefinition()`
- Removed unused method `Doctrine\DBAL\Schema\SqliteSchemaManager::_getPortableTableIndexDefinition()`

## BC BREAK: Changes in the `Doctrine\DBAL\Driver` API

1. The `$username` and `$password` arguments of `::connect()` are no longer nullable. Use an empty string to indicate empty username or password.
2. The return value of `::getDatabase()` has been documented as nullable since some of the drivers allow establishing a connection without selecting a database.

## BC BREAK: `Doctrine\DBAL\Driver::getName()` removed

The `Doctrine\DBAL\Driver::getName()` has been removed.

## BC BREAK Removed previously deprecated features

 * Removed `json_array` type and all associated hacks.
 * Removed `Connection::TRANSACTION_*` constants.
 * Removed `AbstractPlatform::DATE_INTERVAL_UNIT_*` and `AbstractPlatform::TRIM_*` constants.
 * Removed `MysqlSessionInit` listener.
 * Removed `MysqlPlatform::getCollationFieldDeclaration()`.
 * Removed `AbstractPlatform::getIdentityColumnNullInsertSQL()`.
 * Removed `Table::addUnnamedForeignKeyConstraint()` and `Table::addNamedForeignKeyConstraint()`.
 * Removed `Table::renameColumn()`.
 * Removed `SQLParserUtils::getPlaceholderPositions()`.
 * Removed `LoggerChain::addLogger`.
 * Removed `AbstractSchemaManager::getFilterSchemaAssetsExpression()`, `Configuration::getFilterSchemaAssetsExpression()`
   and `Configuration::getFilterSchemaAssetsExpression()`.
 * `SQLParserUtils::*_TOKEN` constants made private.

## BC BREAK `Connection::ping()` returns `void`.

`Connection::ping()` and `PingableConnection::ping()` no longer return a boolean value. They will throw an exception in case of failure.

## BC BREAK PostgreSqlPlatform ForeignKeyConstraint support for `feferred` misspelling removed

`PostgreSqlPlatform::getAdvancedForeignKeyOptionsSQL()` had a typo in it in 2.x. Both the option name
`feferred` and `deferred` were supported in `2.x` but the misspelling was removed in 3.x.

## BC BREAK `AbstractSchemaManager::extractDoctrineTypeFromComment()` changed, `::removeDoctrineTypeFromComment()` removed

`AbstractSchemaManager::extractDoctrineTypeFromComment()` made `protected`. It takes the comment by reference, removes the type annotation from it and returns the extracted Doctrine type.

## BC BREAK Changes in driver exceptions

1. The `Doctrine\DBAL\Driver\DriverException::getErrorCode()` method is removed. In order to obtain the driver error code, please use `::getCode()`.
2. `Doctrine\DBAL\Driver\PDOException` no longer extends `PDOException`.
3. The value returned by `Doctrine\DBAL\Driver\PDOException::getSQLState()` no longer falls back to the driver error code.

The method was used internally and is no longer needed.

## BC BREAK `DB2SchemaManager::_getPortableForeignKeyRuleDef()` removed

The method was used internally and is no longer needed.

## BC BREAK `AbstractPlatform::get*Expression()` methods no loner accept integer values as arguments

The following methods' arguments do not longer accept integer value:

- the `$expression` argument in `::getCountExpression()`,
- the `$decimals` argument in `::getRoundExpression()`,
- the `$seconds` argument in `::getDateAddSecondsExpression()`,
- the `$seconds` argument in `::getDateSubSecondsExpression()`,
- the `$minutes` argument in `::getDateAddMinutesExpression()`,
- the `$minutes` argument in `::getDateSubMinutesExpression()`,
- the `$hours` argument in `::getDateAddHourExpression()`,
- the `$hours` argument in `::getDateAddHourExpression()`,
- the `$days` argument in `::getDateAddDaysExpression()`,
- the `$days` argument in `::getDateSubDaysExpression()`,
- the `$weeks` argument in `::getDateAddWeeksExpression()`,
- the `$weeks` argument in `::getDateSubWeeksExpression()`,
- the `$months` argument in `::getDateAddMonthExpression()`,
- the `$months` argument in `::getDateSubMonthExpression()`,
- the `$quarters` argument in `::getDateAddQuartersExpression()`,
- the `$quarters` argument in `::getDateSubQuartersExpression()`,
- the `$years` argument in `::getDateAddYearsExpression()`,
- the `$years` argument in `::getDateSubYearsExpression()`.

Please use the strings representing numeric SQL literals instead (e.g. `'1'` instead of `1`).

The signature of `AbstractPlatform::getConcatExpression()` changed to `::getConcatExpression(string ...$string)`.

## BC BREAK The type of `$start` in `AbstractPlatform::getLocateExpression()` changed from `string|false` to `?string`

The default value of `$start` is now `null`, not `false`.

## BC BREAK The types of `$start` and `$length` in `AbstractPlatform::getSubstringExpression()` changed from `int` and `?int` to `string` and `?string` respectively

The platform abstraction allows building arbitrary SQL expressions, so even if the arguments represent numeric literals, they should be passed as a string.

## BC BREAK The type of `$char` in `AbstractPlatform::getTrimExpression()` changed from `string|false` to `?string`

The default value of `$char` is now `null`, not `false`. Additionally, the method will throw an `InvalidArgumentException` in an invalid value of `$mode` is passed.

## BC BREAK `Statement::quote()` only accepts strings.

`Statement::quote()` and `ExpressionBuilder::literal()` no longer accept arguments of an arbitrary type and and don't implement type-specific handling. Only strings can be quoted.

## BC BREAK `Statement` and `Connection` methods return `void`.

`Connection::connect()`, `Statement::bindParam()`, `::bindValue()`, `::execute()`, `ResultStatement::setFetchMode()` and `::closeCursor()` no longer return a boolean value. They will throw an exception in case of failure.

## BC BREAK `Statement::rowCount()` is moved.

`Statement::rowCount()` has been moved to the `ResultStatement` interface where it belongs by definition.

## BC BREAK Transaction-related `Statement` methods return `void`.

`Statement::beginTransaction()`, `::commit()` and `::rollBack()` no longer return a boolean value. They will throw a `DriverException` in case of failure.

## MINOR BC BREAK `Statement::fetchColumn()` with an invalid index.

Similarly to `PDOStatement::fetchColumn()`, DBAL statements throw an exception in case of an invalid column index.

## BC BREAK `Statement::execute()` with redundant parameters.

Similarly to the drivers based on `pdo_pgsql` and `pdo_sqlsrv`, `OCI8Statement::execute()` and `MySQLiStatement::execute()` do not longer ignore redundant parameters.

## BC BREAK: `Doctrine\DBAL\Types\Type::getDefaultLength()` removed

The `Doctrine\DBAL\Types\Type::getDefaultLength()` method has been removed as it served no purpose.

## BC BREAK: `Doctrine\DBAL\Types\Type::__toString()` removed

Relying on string representation was discouraged and has been removed.

## BC BREAK: The `NULL` value of `$offset` in LIMIT queries is not allowed

The `NULL` value of the `$offset` argument in `AbstractPlatform::(do)?ModifyLimitQuery()` methods is no longer allowed. The absence of the offset should be indicated with a `0` which is now the default value.

## BC BREAK: Removed support for DB-generated UUIDs

The support for DB-generated UUIDs was removed as non-portable.
Please generate UUIDs on the application side (e.g. using [ramsey/uuid](https://packagist.org/packages/ramsey/uuid)).

## BC BREAK: Removed Doctrine\DBAL\Version

The Doctrine\DBAL\Version class is no longer available: please refrain from checking the DBAL version at runtime.

## BC BREAK: SQLLogger changes

- The `SQLLogger` interface has changed; the methods are the same but use scalar type hints, return types, and non-nullable arrays.
- `SQLLogger` implementations: `DebugStack`, `EchoSQLLogger`, `LoggerChain` are now final.
- `Configuration::getSQLLogger()` does not return `null` anymore, but a `NullLogger` implementation.
- `Configuration::setSQLLogger()` does not allow `null` anymore.

## BC BREAK: Changes to handling binary fields

- Binary fields whose length exceeds the maximum field size on a given platform are no longer represented as `BLOB`s.
  Use binary fields of a size which fits all target platforms, or use blob explicitly instead.
- Binary fields are no longer represented as streams in PHP. They are represented as strings.

# Upgrade to 3.0

## BC BREAK changes in fetching statement results

1. The `Statement` interface no longer extends `ResultStatement`.
2. The `ResultStatement` interface has been renamed to `Result`.
3. Instead of returning `bool`, `Statement::execute()` now returns a `Result` that should be used for fetching the result data and metadata.
4. The functionality previously available via `Statement::closeCursor()` is now available via `Result::free()`. The behavior of fetching data from a freed result is no longer portable. In this case, some drivers will return `false` while others may throw an exception.

Additional related changes:

1. The `ArrayStatement` and `ResultCacheStatement` classes from the `Cache` package have been renamed to `ArrayResult` and  `CachingResult` respectively and marked `@internal`.

## BC BREAK `Statement::rowCount()` is moved.

`Statement::rowCount()` has been moved to the `ResultStatement` interface where it belongs by definition.

## Removed `FetchMode` and the corresponding methods

1. The `FetchMode` class and the `setFetchMode()` method of the `Connection` and `Statement` interfaces are removed.
2. The `Statement::fetch()` method is replaced with `fetchNumeric()`, `fetchAssociative()` and `fetchOne()`.
3. The `Statement::fetchAll()` method is replaced with `fetchAllNumeric()`, `fetchAllAssociative()` and `fechColumn()`.
4. The `Statement::fetchColumn()` method is replaced with `fetchOne()`.
5. The `Connection::fetchArray()` and `fetchAssoc()` methods are replaced with `fetchNumeric()` and `fetchAssociative()` respectively.
6. The `StatementIterator` class is removed. The usage of a `Statement` object as `Traversable` is no longer possible. Use `iterateNumeric()`, `iterateAssociative()` and `iterateColumn()` instead.
7. Fetching data in mixed mode (former `FetchMode::MIXED`) is no longer possible.

## BC BREAK: Dropped handling of one-based numeric arrays of parameters in `Statement::execute()`

The statement implementations no longer detect whether `$params` is a zero- or one-based array. A zero-based numeric array is expected.

## BC BREAK `Statement::project()` has been removed

- The `Statement::project()` method has been removed. Use `::executeQuery()` and fetch the data from the statement using one of the `Statement::fetch*()` methods instead.

## BC BREAK `::errorCode()` and `::errorInfo()` removed from `Connection` and `Statement` APIs

The error information is available in `DriverException` thrown in case of an error.

## BC BREAK: Dropped support for `FetchMode::CUSTOM_OBJECT` and `::STANDARD_OBJECT`

Instead of fetching an object, fetch an array and map it to an object of the desired class.

## BC BREAK: Dropped support for the `$columnIndex` argument in `ResultStatement::fetchColumn()`, other `ResultStatement::fetch*()` methods invoked with `FetchMode::COLUMN` and `Connection::fetchColumn()`.

In order to fetch a column with an index other than `0`, use `FetchMode::NUMERIC` and the array element with the corresponding index.

## BC BREAK: Removed `EchoSQLLogger`

`EchoSQLLogger` is no longer available as part of the package.

## BC BREAK: Removed support for SQL Anywhere 12 and older

DBAL now requires SQL Anywhere 16 or newer, support for unmaintained versions has been dropped.
If you are using any of the legacy versions, you have to upgrade to a newer SQL Anywhere version (16+).

The following classes have been removed:

 * `Doctrine\DBAL\Platforms\SQLAnywherePlatform`
 * `Doctrine\DBAL\Platforms\SQLAnywhere11Platform`
 * `Doctrine\DBAL\Platforms\SQLAnywhere12Platform`
 * `Doctrine\DBAL\Platforms\Keywords\SQLAnywhereKeywords`
 * `Doctrine\DBAL\Platforms\Keywords\SQLAnywhere11Keywords`
 * `Doctrine\DBAL\Platforms\Keywords\SQLAnywhere12Keywords`

## BC BREAK: Removed support for PostgreSQL 9.3 and older

DBAL now requires PostgreSQL 9.4 or newer, support for unmaintained versions has been dropped.
If you are using any of the legacy versions, you have to upgrade to a newer PostgreSQL version (9.6+ is recommended).

The following classes have been removed:

 * `Doctrine\DBAL\Platforms\PostgreSqlPlatform`
 * `Doctrine\DBAL\Platforms\PostgreSQL91Platform`
 * `Doctrine\DBAL\Platforms\PostgreSQL92Platform`
 * `Doctrine\DBAL\Platforms\Keywords\PostgreSQLKeywords`
 * `Doctrine\DBAL\Platforms\Keywords\PostgreSQL91Keywords`
 * `Doctrine\DBAL\Platforms\Keywords\PostgreSQL92Keywords`

## BC BREAK: Removed support for MariaDB 10.0 and older

DBAL now requires MariaDB 10.1 or newer, support for unmaintained versions has been dropped.
If you are using any of the legacy versions, you have to upgrade to a newer MariaDB version (10.1+ is recommended).

## BC BREAK: PingableConnection and ServerInfoAwareConnection interfaces now extend Connection

All implementations of the `PingableConnection` and `ServerInfoAwareConnection` interfaces have to implement the methods defined in the `Connection` interface as well.

## BC BREAK: VersionAwarePlatformDriver interface now extends Driver

All implementations of the `VersionAwarePlatformDriver` interface have to implement the methods defined in the `Driver` interface as well.

## BC BREAK: Removed MsSQLKeywords class

The `Doctrine\DBAL\Platforms\MsSQLKeywords` class has been removed.
Please use `Doctrine\DBAL\Platforms\SQLServerPlatform `instead.

## BC BREAK: Removed PDO DB2 driver

This PDO-based IBM DB2 driver (built on top of `pdo_ibm` extension) has already been unsupported as of 2.5, it has been now removed.

The following class has been removed:

 * `Doctrine\DBAL\Driver\PDOIbm\Driver`

## BC BREAK: Removed support for SQL Server 2008 and older

DBAL now requires SQL Server 2012 or newer, support for unmaintained versions has been dropped.
If you are using any of the legacy versions, you have to upgrade to a newer SQL Server version.

The following classes have been removed:

 * `Doctrine\DBAL\Platforms\SQLServerPlatform`
 * `Doctrine\DBAL\Platforms\SQLServer2005Platform`
 * `Doctrine\DBAL\Platforms\SQLServer2008Platform`
 * `Doctrine\DBAL\Platforms\Keywords\SQLServerKeywords`
 * `Doctrine\DBAL\Platforms\Keywords\SQLServer2005Keywords`
 * `Doctrine\DBAL\Platforms\Keywords\SQLServer2008Keywords`

The `AbstractSQLServerDriver` class and its subclasses no longer implement the `VersionAwarePlatformDriver` interface.

## BC BREAK: Removed Doctrine\DBAL\Version

The `Doctrine\DBAL\Version` class is no longer available: please refrain from checking the DBAL version at runtime.

## BC BREAK User-provided `PDO` instance is no longer supported

In order to share the same `PDO` instances between DBAL and other components, initialize the connection in DBAL and access it using `Connection::getWrappedConnection()->getWrappedConnection()`.

## BC BREAK: the PDO symbols are no longer part of the DBAL API

1. The support of `PDO::PARAM_*`, `PDO::FETCH_*`, `PDO::CASE_*` and `PDO::PARAM_INPUT_OUTPUT` constants in the DBAL API is removed.
2. `\Doctrine\DBAL\Driver\PDOConnection` does not extend `\PDO` anymore. Please use `\Doctrine\DBAL\Driver\PDOConnection::getWrappedConnection()` to access the underlying `PDO` object.
3. `\Doctrine\DBAL\Driver\PDOStatement` does not extend `\PDOStatement` anymore.

Before:

    use Doctrine\DBAL\Portability\Connection;

    $params = array(
        'wrapperClass' => Connection::class,
        'fetch_case' => PDO::CASE_LOWER,
    );

    $stmt->bindValue(1, 1, PDO::PARAM_INT);
    $stmt->fetchAll(PDO::FETCH_COLUMN);

After:

    use Doctrine\DBAL\ColumnCase;
    use Doctrine\DBAL\FetchMode;
    use Doctrine\DBAL\ParameterType;
    use Doctrine\DBAL\Portability\Connection;

    $params = array(
        'wrapperClass' => Connection::class,
        'fetch_case' => ColumnCase::LOWER,
    );

    $stmt->bindValue(1, 1, ParameterType::INTEGER);
    $stmt->fetchAll(FetchMode::COLUMN);

## BC BREAK: Removed Drizzle support

The Drizzle project is abandoned and is therefore not supported by Doctrine DBAL anymore.

## BC BREAK: Removed dbal:import CLI command

The `dbal:import` CLI command has been removed since it only worked with PDO-based drivers by relying on a non-documented behavior of the extension, and it was impossible to make it work with other drivers.
Please use other database client applications for import, e.g.:

 * For MySQL and MariaDB: `mysql [dbname] < data.sql`.
 * For PostgreSQL: `psql [dbname] < data.sql`.
 * For SQLite: `sqlite3 /path/to/file.db < data.sql`.

# Upgrade to 2.11

## Deprecated `ArrayStatement` and `ResultCacheStatement` classes.

The `ArrayStatement` and `ResultCacheStatement` classes are deprecated. In a future major release they will be renamed and marked internal as implementation details of the caching layer.

## Deprecated `ResultStatement` interface

1. The `ResultStatement` interface is deprecated. Use the `Driver\Result` and `Abstraction\Result` interfaces instead.
2. `ResultStatement::closeCursor()` is deprecated in favor of `Result::free()`.

## Deprecated `FetchMode` and the corresponding methods

1. The `FetchMode` class and the `setFetchMode()` method of the `Connection` and `Statement` interfaces are deprecated.
2. The `Statement::fetch()` method is deprecated in favor of `Result::fetchNumeric()`, `::fetchAssociative()` and `::fetchOne()`.
3. The `Statement::fetchAll()` method is deprecated in favor of `Result::fetchAllNumeric()`, `::fetchAllAssociative()` and `::fetchFirstColumn()`.
4. The `Statement::fetchColumn()` method is deprecated in favor of `Result::fetchOne()`.
5. The `Connection::fetchArray()` and `fetchAssoc()` method are deprecated in favor of `fetchNumeric()` and `fetchAssociative()` respectively.
6. The `StatementIterator` class and the usage of a `Statement` object as `Traversable` is deprecated in favor of `Result::iterateNumeric()`, `::iterateAssociative()` and `::iterateColumn()`.
7. Fetching data in mixed mode (`FetchMode::MIXED`) is deprecated.

## Deprecated `Connection::project()`

The `Connection::project()` method is deprecated. Implement data transformation outside of DBAL.

## Deprecated `Statement::errorCode()` and `errorInfo()`

The `Statement::errorCode()` and `errorInfo()` methods are deprecated. The error information is available via exceptions.

## Deprecated `EchoSQLLogger`

The `EchoSQLLogger` class is deprecated. Implement your logger with the desired logic.

## Deprecated database platforms:

1. PostgreSQL 9.3 and older
2. MariaDB 10.0 and older
3. SQL Server 2008 and older
4. SQL Anywhere 12 and older
5. Drizzle
6. Azure SQL Database

## Deprecated database drivers:

1. PDO-based IBM DB2 driver
2. Drizzle MySQL driver

## Deprecated `Doctrine\DBAL\Sharding` package

The sharding functionality in DBAL has been effectively unmaintained for a long time.

## Deprecated `Doctrine\DBAL\Version` class

The usage of the `Doctrine\DBAL\Version` class is deprecated as internal implementation detail. Please refrain from checking the DBAL version at runtime.

## Deprecated `ExpressionBuilder` methods

The usage of the `andX()` and `orX()` methods of the `ExpressionBuilder` class has been deprecated. Use `and()` and `or()` instead.

## Deprecated `CompositeExpression` methods

- The usage of the `add()` and `addMultiple()` methods of the `CompositeExpression` class has been deprecated. Use `with()` instead, which returns a new instance.
In the future, the `add*()` methods will be removed and the class will be effectively immutable.
- The usage of the `CompositeExpression` constructor has been deprecated. Use the `and()` / `or()` factory methods.

## Deprecated calling `QueryBuilder` methods with an array argument

Calling the `select()`, `addSelect()`, `groupBy()` and `addGroupBy()` methods with an array argument is deprecated.

# Upgrade to 2.10

## Deprecated `Doctrine\DBAL\Event\ConnectionEventArgs` methods

The usage of the `getDriver()`, `getDatabasePlatform()` and `getSchemaManager()` methods of the `ConnectionEventArgs` class has been deprecated. Obtain the underlying connection via `getConnection()` and call the corresponding methods on the connection instance.

## Deprecated `Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs` methods

The usage of the `getDatabasePlatform()` method of the `SchemaColumnDefinitionEventArgs` class has been deprecated. Obtain the underlying connection via `getConnection()` and call the corresponding method on the connection instance.

## Deprecated `Doctrine\DBAL\Connection` methods

The usage of the `getHost()`, `getPort()`, `getUsername()` and `getPassword()` methods of the `Connection` class has been deprecated as they leak implementation details.

## Deprecated array of statements in `addSql()` of `SchemaEventArgs`-based classes.

Passing multiple SQL statements as an array to `SchemaAlterTableAddColumnEventArgs::addSql()` and the same method in other `SchemaEventArgs`-based classes is deprecated. Pass each statement as an individual argument instead.

## Deprecated calling `AbstractSchemaManager::tablesExist()` with a string argument.

Instead of passing a string, pass a one-element array.

## Deprecated calling `OracleSchemaManager::createDatabase()` without an argument or by passing NULL.

In order to create a database, always pass the database name.

## Deprecated unused schema manager methods.

The following methods have been deprecated as unused:

- `AbstractSchemaManager::_getPortableFunctionsList()`,
- `AbstractSchemaManager::_getPortableFunctionDefinition()`,
- `OracleSchemaManager::_getPortableFunctionDefinition()`,
- `SqliteSchemaManager::_getPortableTableIndexDefinition()`.

# Deprecations in `Doctrine\DBAL\Driver`

- The usage of NULL to indicate empty `$username` or `$password` when calling `connect()` is deprecated. Use an empty string instead.

## Deprecated `Doctrine\DBAL\Platforms::_getAlterTableIndexForeignKeySQL()`

Method `Doctrine\DBAL\Platforms::_getAlterTableIndexForeignKeySQL()` has been deprecated as no longer used.

## Deprecated `Doctrine\DBAL\Driver\OCI8\OCI8Statement::$_PARAM`

Property `Doctrine\DBAL\Driver\OCI8\OCI8Statement::$_PARAM` has been deprecated as not used.

## Deprecated `Doctrine\DBAL\Driver::getName()`

Relying on the name of the driver is discouraged. For referencing the driver, use its class name.

## Deprecated usage of user-provided `PDO` instance

The usage of user-provided `PDO` instance is deprecated. The known use cases are:

1. **Persistent PDO connections.** DBAL 3.0 will supported establishing persistent connections, therefore, providing a pre-created persistent PDO connection will be no longer needed.
2. **Sharing `PDO` instance between DBAL and legacy components.** In order to share a PDO instance, initialize the connection in DBAL and access it using `Connection::getWrappedConnection()->getWrappedConnection()`.

## MINOR BC BREAK: Default values are no longer handled as SQL expressions

They are converted to SQL literals (e.g. escaped). Clients must now specify default values in their initial form, not in the form of an SQL literal (e.g. escaped).

Before:

    $column->setDefault('Foo\\\\Bar\\\\Baz');

After:

    $column->setDefault('Foo\\Bar\\Baz');

## Deprecated `Type::*` constants

The constants for built-in types have been moved from `Doctrine\DBAL\Types\Type` to a separate class `Doctrine\DBAL\Types\Types`.

Some of the constants were renamed in the process:
* `TARRAY`-> `ARRAY`
* `DATE` -> `DATE_MUTABLE`
* `DATETIME` -> `DATETIME_MUTABLE`
* `DATETIMETZ` -> `DATETIMETZ_MUTABLE`
* `TIME` -> `TIME_MUTABLE`

## Deprecated `SQLSrvStatement::LAST_INSERT_ID_SQL` constant

The  `Doctrine\DBAL\Driver\SQLSrv\SQLSrvStatement::LAST_INSERT_ID_SQL` constant has been deprecated and will be made private in 3.0.

## Deprecated `SQLParserUtils` constants

The constants in `Doctrine\DBAL\SQLParserUtils` have been deprecated and will be made private in 3.0.

## Deprecated `LoggerChain::addLogger` method

The `Doctrine\DBAL\Logging\LoggerChain::addLogger` method has been deprecated. Inject list of loggers via constructor instead.

# Upgrade to 2.9

## Deprecated `Statement::fetchColumn()` with an invalid index

Calls to `Statement::fetchColumn()` with an invalid column index currently return `NULL`. In the future, such calls will result in a exception.

## Deprecated `Configuration::getFilterSchemaAssetsExpression()`, `::setFilterSchemaAssetsExpression()` and `AbstractSchemaManager::getFilterSchemaAssetsExpression()`.

Regular expression-based filters are hard to extend by combining together. Instead, you may use callback-based filers via `::getSchemaAssetsFilter()` and `::getSchemaAssetsFilter()`. Callbacks can use regular expressions internally.

## Deprecated `Doctrine\DBAL\Types\Type::getDefaultLength()`

This method was never used by DBAL internally. It is now deprecated and will be removed in DBAL 3.0.

## Deprecated `Doctrine\DBAL\Types\Type::__toString()`

Relying on string representation is discouraged and will be removed in DBAL 3.0.

## Deprecated `NULL` value of `$offset` in LIMIT queries

The `NULL` value of the `$offset` argument in `AbstractPlatform::(do)?ModifyLimitQuery()` methods is deprecated. If explicitly used in the method call, the absence of the offset should be indicated with a `0`.

## Deprecated dbal:import CLI command

The `dbal:import` CLI command has been deprecated since it only works with PDO-based drivers by relying on a non-documented behavior of the extension, and it's impossible to make it work with other drivers.
Please use other database client applications for import, e.g.:

 * For MySQL and MariaDB: `mysql [dbname] < data.sql`.
 * For PostgreSQL: `psql [dbname] < data.sql`.
 * For SQLite: `sqlite3 /path/to/file.db < data.sql`.

# Upgrade to 2.8

## Deprecated usage of DB-generated UUIDs

The format of DB-generated UUIDs is inconsistent across supported platforms and therefore is not portable. Some of the platforms produce UUIDv1, some produce UUIDv4, some produce the values which are not even UUID.

Unless UUIDs are used in stored procedures which DBAL doesn't support, there's no real benefit of DB-generated UUIDs comparing to the application-generated ones.

Use a PHP library (e.g. [ramsey/uuid](https://packagist.org/packages/ramsey/uuid)) to generate UUIDs on the application side.

## Deprecated usage of binary fields whose length exceeds the platform maximum

- The usage of binary fields whose length exceeds the maximum field size on a given platform is deprecated.
  Use binary fields of a size which fits all target platforms, or use blob explicitly instead.

## Removed dependency on doctrine/common

The dependency on doctrine/common package has been removed.
DBAL now depends on doctrine/cache and doctrine/event-manager instead.
If you are using any other component from doctrine/common package,
you will have to add an explicit dependency to your composer.json.

## Corrected exception thrown by ``Doctrine\DBAL\Platforms\SQLAnywhere16Platform::getAdvancedIndexOptionsSQL()``

This method now throws SPL ``UnexpectedValueException`` instead of accidentally throwing ``Doctrine\Common\Proxy\Exception\UnexpectedValueException``.

# Upgrade to 2.7

## Doctrine\DBAL\Platforms\AbstractPlatform::DATE_INTERVAL_UNIT_* constants deprecated

``Doctrine\DBAL\Platforms\AbstractPlatform::DATE_INTERVAL_UNIT_*`` constants were moved into ``Doctrine\DBAL\Platforms\DateIntervalUnit`` class without the ``DATE_INTERVAL_UNIT_`` prefix.

## Doctrine\DBAL\Platforms\AbstractPlatform::TRIM_* constants deprecated

``Doctrine\DBAL\Platforms\AbstractPlatform::TRIM_*`` constants were moved into ``Doctrine\DBAL\Platforms\TrimMode`` class without the ``TRIM_`` prefix.

## Doctrine\DBAL\Connection::TRANSACTION_* constants deprecated

``Doctrine\DBAL\Connection::TRANSACTION_*`` were moved into ``Doctrine\DBAL\TransactionIsolationLevel`` class without the ``TRANSACTION_`` prefix.

## DEPRECATION: direct usage of the PDO APIs in the DBAL API

1. When calling `Doctrine\DBAL\Driver\Statement` methods, instead of `PDO::PARAM_*` constants, `Doctrine\DBAL\ParameterType` constants should be used.
2. When calling `Doctrine\DBAL\Driver\ResultStatement` methods, instead of `PDO::FETCH_*` constants, `Doctrine\DBAL\FetchMode` constants should be used.
3. When configuring `Doctrine\DBAL\Portability\Connection`, instead of `PDO::CASE_*` constants, `Doctrine\DBAL\ColumnCase` constants should be used.
4. Usage of `PDO::PARAM_INPUT_OUTPUT` in `Doctrine\DBAL\Driver\Statement::bindValue()` is deprecated.
5. Usage of `PDO::FETCH_FUNC` in `Doctrine\DBAL\Driver\ResultStatement::fetch()` is deprecated.
6. Calls to `\PDOStatement` methods on a `\Doctrine\DBAL\Driver\PDOStatement` instance (e.g. `fetchObject()`) are deprecated.

# Upgrade to 2.6

## MINOR BC BREAK: `fetch()` and `fetchAll()` method signatures in `Doctrine\DBAL\Driver\ResultStatement`

1. ``Doctrine\DBAL\Driver\ResultStatement::fetch()`` now has 3 arguments instead of 1, respecting
``PDO::fetch()`` signature.

Before:

    Doctrine\DBAL\Driver\ResultStatement::fetch($fetchMode);

After:

    Doctrine\DBAL\Driver\ResultStatement::fetch($fetchMode, $cursorOrientation, $cursorOffset);

2. ``Doctrine\DBAL\Driver\ResultStatement::fetchAll()`` now has 3 arguments instead of 1, respecting
``PDO::fetchAll()`` signature.

Before:

    Doctrine\DBAL\Driver\ResultStatement::fetchAll($fetchMode);

After:

    Doctrine\DBAL\Driver\ResultStatement::fetch($fetchMode, $fetchArgument, $ctorArgs);


## MINOR BC BREAK: URL-style DSN with percentage sign in password

URL-style DSNs (e.g. ``mysql://foo@bar:localhost/db``) are now assumed to be percent-encoded
in order to allow certain special characters in usernames, paswords and database names. If
you are using a URL-style DSN and have a username, password or database name containing a
percentage sign, you need to update your DSN. If your password is, say, ``foo%foo``, it
should be encoded as ``foo%25foo``.

# Upgrade to 2.5.1

## MINOR BC BREAK: Doctrine\DBAL\Schema\Table

When adding indexes to ``Doctrine\DBAL\Schema\Table`` via ``addIndex()`` or ``addUniqueIndex()``,
duplicate indexes are not silently ignored/dropped anymore (based on semantics, not naming!).
Duplicate indexes are considered indexes that pass ``isFullfilledBy()`` or ``overrules()``
in ``Doctrine\DBAL\Schema\Index``.
This is required to make the index renaming feature introduced in 2.5.0 work properly and avoid
issues in the ORM schema tool / DBAL schema manager which pretends users from updating
their schemas and migrate to DBAL 2.5.*.
Additionally it offers more flexibility in declaring indexes for the user and potentially fixes
related issues in the ORM.
With this change, the responsibility to decide which index is a "duplicate" is completely deferred
to the user.
Please also note that adding foreign key constraints to a table via ``addForeignKeyConstraint()``,
``addUnnamedForeignKeyConstraint()`` or ``addNamedForeignKeyConstraint()`` now first checks if an
appropriate index is already present and avoids adding an additional auto-generated one eventually.

# Upgrade to 2.5

## BC BREAK: time type resets date fields to UNIX epoch

When mapping `time` type field to PHP's `DateTime` instance all unused date fields are
reset to UNIX epoch (i.e. 1970-01-01). This might break any logic which relies on comparing
`DateTime` instances with date fields set to the current date.

Use `!` format prefix (see http://php.net/manual/en/datetime.createfromformat.php) for parsing
time strings to prevent having different date fields when comparing user input and `DateTime`
instances as mapped by Doctrine.

## BC BREAK: Doctrine\DBAL\Schema\Table

The methods ``addIndex()`` and ``addUniqueIndex()`` in ``Doctrine\DBAL\Schema\Table``
have an additional, optional parameter. If you override these methods, you should
add this new parameter to the declaration of your overridden methods.

## BC BREAK: Doctrine\DBAL\Connection

The visibility of the property ``$_platform`` in ``Doctrine\DBAL\Connection``
was changed from protected to private. If you have subclassed ``Doctrine\DBAL\Connection``
in your application and accessed ``$_platform`` directly, you have to change the code
portions to use ``getDatabasePlatform()`` instead to retrieve the underlying database
platform.
The reason for this change is the new automatic platform version detection feature,
which lazily evaluates the appropriate platform class to use for the underlying database
server version at runtime.
Please also note, that calling ``getDatabasePlatform()`` now needs to establish a connection
in order to evaluate the appropriate platform class if ``Doctrine\DBAL\Connection`` is not
already connected. Under the following circumstances, it is not possible anymore to retrieve
the platform instance from the connection object without having to do a real connect:

1. ``Doctrine\DBAL\Connection`` was instantiated without the ``platform`` connection parameter.
2. ``Doctrine\DBAL\Connection`` was instantiated without the ``serverVersion`` connection parameter.
3. The underlying driver is "version aware" and can provide different platform instances
   for different versions.
4. The underlying driver connection is "version aware" and can provide the database server
   version without having to query for it.

If one of the above conditions is NOT met, there is no need for ``Doctrine\DBAL\Connection``
to do a connect when calling ``getDatabasePlatform()``.

## datetime Type uses date_create() as fallback

Before 2.5 the DateTime type always required a specific format, defined in
`$platform->getDateTimeFormatString()`, which could cause quite some troubles
on platforms that had various microtime precision formats. Starting with 2.5
whenever the parsing of a date fails with the predefined platform format,
the `date_create()` function will be used to parse the date.

This could cause some troubles when your date format is weird and not parsed
correctly by `date_create`, however since databases are rather strict on dates
there should be no problem.

## Support for pdo_ibm driver removed

The ``pdo_ibm`` driver is buggy and does not work well with Doctrine. Therefore it will no
longer be supported and has been removed from the ``Doctrine\DBAL\DriverManager`` drivers
map. It is highly encouraged to to use `ibm_db2` driver instead if you want to connect
to an IBM DB2 database as it is much more stable and secure.

If for some reason you have to utilize the ``pdo_ibm`` driver you can still use the `driverClass`
connection parameter to explicitly specify the ``Doctrine\DBAL\Driver\PDOIbm\Driver`` class.
However be aware that you are doing this at your own risk and it will not be guaranteed that
Doctrine will work as expected.

# Upgrade to 2.4

## Doctrine\DBAL\Schema\Constraint

If you have custom classes that implement the constraint interface, you have to implement
an additional method ``getQuotedColumns`` now. This method is used to build proper constraint
SQL for columns that need to be quoted, like keywords reserved by the specific platform used.
The method has to return the same values as ``getColumns`` only that those column names that
need quotation have to be returned quoted for the given platform.

# Upgrade to 2.3

## Oracle Session Init now sets Numeric Character

Before 2.3 the Oracle Session Init did not care about the numeric character of the Session.
This could lead to problems on non english locale systems that required a comma as a floating
point seperator in Oracle. Since 2.3, using the Oracle Session Init on connection start the
client session will be altered to set the numeric character to ".,":

    ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,'

See [DBAL-345](http://www.doctrine-project.org/jira/browse/DBAL-345) for more details.

## Doctrine\DBAL\Connection and Doctrine\DBAL\Statement

The query related methods including but not limited to executeQuery, exec, query, and executeUpdate
now wrap the driver exceptions such as PDOException with DBALException to add more debugging
information such as the executed SQL statement, and any bound parameters.

If you want to retrieve the driver specific exception, you can retrieve it by calling the
``getPrevious()`` method on DBALException.

Before:

    catch(\PDOException $ex) {
        // ...
    }

After:

    catch(\Doctrine\DBAL\DBALException $ex) {
        $pdoException = $ex->getPrevious();
        // ...
    }

## Doctrine\DBAL\Connection#setCharsetSQL() removed

This method only worked on MySQL and it is considered unsafe on MySQL to use SET NAMES UTF-8 instead
of setting the charset directly on connection already. Replace this behavior with the
connection charset option:

Before:

    $conn = DriverManager::getConnection(array(..));
    $conn->setCharset('UTF8');

After:

    $conn = DriverManager::getConnection(array('charset' => 'UTF8', ..));

## Doctrine\DBAL\Schema\Table#renameColumn() removed

Doctrine\DBAL\Schema\Table#renameColumn() was removed, because it drops and recreates
the column instead. There is no fix available, because a schema diff
cannot reliably detect if a column was renamed or one column was created
and another one dropped.

You should use explicit SQL ALTER TABLE statements to change columns names.

## Schema Filter paths

The Filter Schema assets expression is not wrapped in () anymore for the regexp automatically.

Before:

    $config->setFilterSchemaAssetsExpression('foo');

After:

    $config->setFilterSchemaAssetsExpression('(foo)');

## Creating MySQL Tables now defaults to UTF-8

If you are creating a new MySQL Table through the Doctrine API, charset/collate are
now set to 'utf8'/'utf8_unicode_ci' by default. Previously the MySQL server defaults were used.

# Upgrade to 2.2

## Doctrine\DBAL\Connection#insert and Doctrine\DBAL\Connection#update

Both methods now accept an optional last parameter $types with binding types of the values passed.
This can potentially break child classes that have overwritten one of these methods.

## Doctrine\DBAL\Connection#executeQuery

Doctrine\DBAL\Connection#executeQuery() got a new last parameter "QueryCacheProfile $qcp"

## Doctrine\DBAL\Driver\Statement split

The Driver statement was split into a ResultStatement and the normal statement extending from it.
This separates the configuration and the retrieval API from a statement.

## MsSql Platform/SchemaManager renamed

The MsSqlPlatform was renamed to SQLServerPlatform, the MsSqlSchemaManager was renamed
to SQLServerSchemaManager.

## Cleanup SQLServer Platform version mess

DBAL 2.1 and before were actually only compatible to SQL Server 2008, not earlier versions.
Still other parts of the platform did use old features instead of newly introduced datatypes
in SQL Server 2005. Starting with DBAL 2.2 you can pick the Doctrine abstraction exactly
matching your SQL Server version.

The PDO SqlSrv driver now uses the new `SQLServer2008Platform` as default platform.
This platform uses new features of SQL Server as of version 2008. This also includes a switch
in the used fields for "text" and "blob" field types to:

    "text" => "VARCHAR(MAX)"
    "blob" => "VARBINARY(MAX)"

Additionally `SQLServerPlatform` in DBAL 2.1 and before used "DATE", "TIME" and "DATETIME2" for dates.
This types are only available since version 2008 and the introduction of an explicit
SQLServer 2008 platform makes this dependency explicit.

An `SQLServer2005Platform` was also introduced to differentiate the features between
versions 2003, earlier and 2005.

With this change the `SQLServerPlatform` now throws an exception for using limit queries
with an offset, since SQLServer 2003 and lower do not support this feature.

To use the old SQL Server Platform, because you are using SQL Server 2003 and below use
the following configuration code:

    use Doctrine\DBAL\DriverManager;
    use Doctrine\DBAL\Platforms\SQLServerPlatform;
    use Doctrine\DBAL\Platforms\SQLServer2005Platform;

    // You are using SQL Server 2003 or earlier
    $conn = DriverManager::getConnection(array(
        'driver' => 'pdo_sqlsrv',
        'platform' => new SQLServerPlatform()
        // .. additional parameters
    ));

    // You are using SQL Server 2005
    $conn = DriverManager::getConnection(array(
        'driver' => 'pdo_sqlsrv',
        'platform' => new SQLServer2005Platform()
        // .. additional parameters
    ));

    // You are using SQL Server 2008
    $conn = DriverManager::getConnection(array(
        'driver' => 'pdo_sqlsrv',
        // 2008 is default platform
        // .. additional parameters
    ));
