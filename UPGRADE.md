Note about upgrading: Doctrine uses static and runtime mechanisms to raise
awareness about deprecated code.

- Use of `@deprecated` docblock that is detected by IDEs (like PHPStorm) or
  Static Analysis tools (like Psalm, phpstan)
- Use of our low-overhead runtime deprecation API, details:
  https://github.com/doctrine/deprecations/

# Upgrade to 3.3

## Deprecated `Type::canRequireSQLConversion()`.

Consumers should call `Type::convertToDatabaseValueSQL()` and `Type::convertToPHPValueSQL()` regardless of the type.

## Deprecated the `doctrine-dbal` binary.

The documentation explains how the console tools can be bootstrapped for standalone usage.

The method `ConsoleRunner::printCliConfigTemplate()` is deprecated because it was only useful in the context of the
`doctrine-dbal` binary.

## Deprecated the `Graphviz` visitor.

This class is not part of the database abstraction provided by the library and will be removed in DBAL 4.

## Deprecated the `--depth` option of `RunSqlCommand`.

This option does not have any effect anymore and will be removed in DBAL 4.

## Deprecated platform "commented type" API

Since `Type::requiresSQLCommentTypeHint()` already allows determining whether a
type should result in SQL columns with a type hint in their comments, the
following methods are deprecated:

- `AbstractPlatform::isCommentedDoctrineType()`
- `AbstractPlatform::initializeCommentedDoctrineTypes()`
- `AbstractPlatform::markDoctrineTypeCommented()`

The protected property `AbstractPlatform::$doctrineTypeComments` is deprecated
as well.

## Deprecated support for IBM DB2 10.5 and older

IBM DB2 10.5 and older won't be supported in DBAL 4. Consider upgrading to IBM DB2 11.1 or later.

## Deprecated support for Oracle 12c (12.2.0.1) and older

Oracle 12c (12.2.0.1) won't be supported in DBAL 4. Consider upgrading to Oracle 18c (12.2.0.2) or later.

## Deprecated support for MariaDB 10.2.6 and older

MariaDB 10.2.6 and older won't be supported in DBAL 4. Consider upgrading to MariaDB 10.2.7 or later.
The following classes have been deprecated:

* `Doctrine\DBAL\Platforms\MariaDb1027Platform`
* `Doctrine\DBAL\Platforms\Keywords\MariaDb102Keywords`

## Deprecated support for MySQL 5.6 and older

MySQL 5.6 and older won't be actively supported in DBAL 4. Consider upgrading to MySQL 5.7 or later.
The following classes have been deprecated:

* `Doctrine\DBAL\Platforms\MySQL57Platform`
* `Doctrine\DBAL\Platforms\Keywords\MySQL57Keywords`

## Deprecated support for Postgres 9

Postgres 9 won't be actively supported in DBAL 4. Consider upgrading to Postgres 10 or later.
The following classes have been deprecated:

* `Doctrine\DBAL\Platforms\PostgreSQL100Platform`
* `Doctrine\DBAL\Platforms\Keywords\PostgreSQL100Keywords`

## Deprecated `Connection::getWrappedConnection()`, `Connection::connect()` made `@internal`.

The wrapper-level `Connection::getWrappedConnection()` method has been deprecated.
Use `Connection::getNativeConnection()` to access the native connection.

The `Connection::connect()` method has been marked internal. It will be marked `protected` in DBAL 4.0.

## Add `Connection::getNativeConnection()`

Driver and middleware connections need to implement a new method `getNativeConnection()` that gives access to the
native database connection. Not doing so is deprecated.

## Deprecate accessors for the native connection in favor of `getNativeConnection()`

The following methods have been deprecated:

* `Doctrine\DBAL\Driver\PDO\Connection::getWrappedConnection()`
* `Doctrine\DBAL\Driver\PDO\SQLSrv\Connection::getWrappedConnection()`
* `Doctrine\DBAL\Driver\Mysqli\Connection::getWrappedResourceHandle()`

Call `getNativeConnection()` to access the underlying PDO or MySQLi connection.

# Upgrade to 3.2

## Minor BC Break: using cache keys with characters reserved by `psr/cache`

We have been working on phasing out `doctrine/cache`, and 3.2.0 allows to use
`psr/cache` instead. To help calling our own internal APIs in a unified way, we
also wrap `doctrine/cache` implementations with a `psr/cache` adapter.
Using cache keys containing characters reserved by `psr/cache` will result in
an exception. The characters are the following: `{}()/\@`.

## Deprecated `SQLLogger` and its implementations.

The `SQLLogger` and its implementations `DebugStack` and `LoggerChain` have been deprecated.
For logging purposes, use `Doctrine\DBAL\Logging\Middleware` instead. No replacement for `DebugStack` is provided.

## Deprecated `SqliteSchemaManager::createDatabase()` and `dropDatabase()` methods.

The `SqliteSchemaManager::createDatabase()` and `dropDatabase()` methods have been deprecated. The SQLite engine
will create the database file automatically. In order to delete the database file, use the filesystem.

## Deprecated `AbstractSchemaManager::dropAndCreate*()` and `::tryMethod()` methods.

The following `AbstractSchemaManager::dropAndCreate*()` methods have been deprecated:

1. `AbstractSchemaManager::dropAndCreateConstraint()`. Use `AbstractSchemaManager::dropIndex()`
   and `AbstractSchemaManager::createIndex()`, `AbstractSchemaManager::dropForeignKey()`
   and `AbstractSchemaManager::createForeignKey()` or `AbstractSchemaManager::dropUniqueConstraint()`
   and `AbstractSchemaManager::createUniqueConstraint()` instead.
2. `AbstractSchemaManager::dropAndCreateIndex()`. Use `AbstractSchemaManager::dropIndex()`
   and `AbstractSchemaManager::createIndex()` instead.
3. `AbstractSchemaManager::dropAndCreateForeignKey()`.
    Use AbstractSchemaManager::dropForeignKey() and AbstractSchemaManager::createForeignKey() instead.
4. `AbstractSchemaManager::dropAndCreateSequence()`. Use `AbstractSchemaManager::dropSequence()`
   and `AbstractSchemaManager::createSequence()` instead.
5. `AbstractSchemaManager::dropAndCreateTable()`. Use `AbstractSchemaManager::dropTable()`
   and `AbstractSchemaManager::createTable()` instead.
6. `AbstractSchemaManager::dropAndCreateDatabase()`. Use `AbstractSchemaManager::dropDatabase()`
   and `AbstractSchemaManager::createDatabase()` instead.
7. `AbstractSchemaManager::dropAndCreateView()`. Use `AbstractSchemaManager::dropView()`
   and `AbstractSchemaManager::createView()` instead.

The `AbstractSchemaManager::tryMethod()` method has been also deprecated.

## Deprecated `AbstractSchemaManager::getSchemaSearchPaths()`.

1. The `AbstractSchemaManager::getSchemaSearchPaths()` method has been deprecated.
2. Relying on `AbstractSchemaManager::createSchemaConfig()` populating the schema name for those database
   platforms that don't support schemas (currently, all except for PostgreSQL) is deprecated.
3. Relying on `Schema` using "public" as the default name is deprecated.

## Deprecated `AbstractAsset::getFullQualifiedName()`.

The `AbstractAsset::getFullQualifiedName()` method has been deprecated. Use `::getNamespaceName()`
and `::getName()` instead.

## Deprecated schema methods related to explicit foreign key indexes.

The following methods have been deprecated:

- `Schema::hasExplicitForeignKeyIndexes()`,
- `SchemaConfig::hasExplicitForeignKeyIndexes()`,
- `SchemaConfig::setExplicitForeignKeyIndexes()`.

## Deprecated `Schema::getTableNames()`.

The `Schema::getTableNames()` method has been deprecated. In order to obtain schema table names,
use `Schema::getTables()` and call `Table::getName()` on the elements of the returned array.

## Deprecated features of `Schema::getTables()`

Using the returned array keys as table names is deprecated. Retrieve the name from the table
via `Table::getName()` instead. In order to retrieve a table by name, use `Schema::getTable()`.

## Deprecated `AbstractPlatform::canEmulateSchemas()`.

The `AbstractPlatform::canEmulateSchemas()` method and the schema emulation implemented in the SQLite platform
have been deprecated.

## Deprecated `udf*` methods of the `SQLitePlatform` methods.

The following `SQLServerPlatform` methods have been deprecated in favor of their implementations
in the `UserDefinedFunctions` class:
- `udfSqrt()`,
- `udfMod()`,
- `udfLocate()`.

## `SQLServerPlatform` methods marked internal.

The following `SQLServerPlatform` methods have been marked internal:
- `getDefaultConstraintDeclarationSQL()`,
- `getAddExtendedPropertySQL()`,
- `getDropExtendedPropertySQL()`,
- `getUpdateExtendedPropertySQL()`.

## `OraclePlatform` methods marked internal.

The `OraclePlatform::getCreateAutoincrementSql()` and `::getDropAutoincrementSql()` have been marked internal.

## Deprecated `OraclePlatform::assertValidIdentifier()`

The `OraclePlatform::assertValidIdentifier()` method has been deprecated.

## Deprecated features of `Table::getColumns()`

1. Using the returned array keys as column names is deprecated. Retrieve the name from the column
   via `Column::getName()` instead. In order to retrieve a column by name, use `Table::getColumn()`.
2. Relying on the columns being sorted based on whether they belong to the primary key or a foreign key is deprecated.
   If necessary, maintain the column order explicitly.

## Deprecated not passing the `$fromColumn` argument to the `ColumnDiff` constructor.

Not passing the `$fromColumn` argument to the `ColumnDiff` constructor is deprecated.

## Deprecated `AbstractPlatform::getName()`

Relying on the name of the platform is discouraged. To identify the platform, use its class name.

## Deprecated versioned platform classes that represent the lowest supported version:

1. `PostgreSQL94Platform` and `PostgreSQL94Keywords`. Use `PostgreSQLPlatform` and `PostgreSQLKeywords` instead.
2. `SQLServer2012Platform` and `SQLServer2012Keywords`. Use `SQLServerPlatform` and `SQLServerKeywords` instead.

## Deprecated schema comparison APIs that don't account for the current database connection and the database platform

1. Instantiation of the `Comparator` class outside the DBAL is deprecated. Use `SchemaManager::createComparator()`
   to create the comparator specific to the current database connection and the database platform.
2. The `Schema::getMigrateFromSql()` and `::getMigrateToSql()` methods are deprecated. Compare the schemas using the
   connection-aware comparator and produce the SQL by passing the resulting diff to the target platform.

## Deprecated driver-level APIs that don't take the server version into account.

The `ServerInfoAwareConnection` and `VersionAwarePlatformDriver` interfaces are deprecated. In the next major version,
all drivers and driver connections will be required to implement the APIs aware of the server version.

## Deprecated `AbstractPlatform::prefersIdentityColumns()`.

Whether to use identity columns should be decided by the application developer. For example, based on the set
of supported database platforms.

## Deprecated `AbstractPlatform::getNowExpression()`.

Relying on dates generated by the database is deprecated. Generate dates within the application.

## Deprecated reference from `ForeignKeyConstraint` to its local (referencing) `Table`.

Reference from `ForeignKeyConstraint` to its local (referencing) `Table` is deprecated as well as the following methods:

- `setLocalTable()`,
- `getLocalTable()`,
- `getLocalTableName()`.

When a foreign key is used as part of the `Table` definition, the table should be used directly. When a foreign key is
used as part of another collection (e.g. `SchemaDiff`), the collection should store the reference to the key's
referencing table separately.

## Deprecated redundant `AbstractPlatform` methods.

The following methods implement simple SQL fragments that don't vary across supported platforms. The SQL fragments
implemented by these methods should be used as is:

- `getSqlCommentStartString()`,
- `getSqlCommentEndString()`,
- `getWildcards()`,
- `getAvgExpression()`,
- `getCountExpression()`,
- `getMaxExpression()`,
- `getMinExpression()`,
- `getSumExpression()`,
- `getMd5Expression()`,
- `getSqrtExpression()`,
- `getRoundExpression()`,
- `getRtrimExpression()`,
- `getLtrimExpression()`,
- `getUpperExpression()`,
- `getLowerExpression()`,
- `getNotExpression()`,
- `getIsNullExpression()`,
- `getIsNotNullExpression()`,
- `getBetweenExpression()`,
- `getAcosExpression()`,
- `getSinExpression()`,
- `getPiExpression()`,
- `getCosExpression()`,
- `getTemporaryTableSQL()`,
- `getUniqueFieldDeclarationSQL()`.

The `getListUsersSQL()` method is not implemented by any of the supported platforms.

The following methods describe the features consistently implemented across all the supported platforms:

- `supportsIndexes()`,
- `supportsAlterTable()`,
- `supportsTransactions()`,
- `supportsPrimaryConstraints()`,
- `supportsViews()`,
- `supportsLimitOffset()`.

All 3rd-party platform implementations must implement the support for these features as well.

The `supportsGettingAffectedRows()` method describes a driver-level feature and does not belong to the Platform API.

## Deprecated `AbstractPlatform` methods that describe the default and the maximum column lengths.

Relying on the default and the maximum column lengths provided by the DBAL is deprecated.
The following `AbstractPlatform` methods and their implementations in specific platforms have been deprecated:

- `getCharMaxLength()`,
- `getVarcharDefaultLength()`,
- `getVarcharMaxLength()`,
- `getBinaryDefaultLength()`,
- `getBinaryMaxLength()`.

If required by the target platform(s), the column length should be specified based on the application logic.

## Deprecated static calls to `Comparator::compareSchemas($fromSchema, $toSchema)`

The usage of `Comparator::compareSchemas($fromSchema, $toSchema)` statically is
deprecated in order to provide a more consistent API.

## Deprecated `Comparator::compare($fromSchema, $toSchema)`

The usage of `Comparator::compare($fromSchema, $toSchema)` is deprecated and 
replaced by `Comparator::compareSchemas($fromSchema, $toSchema)` in order to
clarify the purpose of the method.

## Deprecated `Connection::lastInsertId($name)`

The usage of `Connection::lastInsertId()` with a sequence name is deprecated as unsafe in scenarios with multiple
concurrent connections. If a newly inserted row needs to be referenced, it is recommended to generate its identifier
explicitly prior to insertion.

## Introduction of PSR-6 for result caching

Instead of relying on the deprecated `doctrine/cache` library, a PSR-6 cache
can now be used for result caching. The usage of Doctrine Cache is deprecated
in favor of PSR-6. The following methods related to Doctrine Cache have been
replaced with PSR-6 counterparts:

| class               | old method               | new method         |
| ------------------- | ------------------------ | ------------------ |
| `Configuration`     | `setResultCacheImpl()`   | `setResultCache()` |
| `Configuration`     | `getResultCacheImpl()`   | `getResultCache()` |
| `QueryCacheProfile` | `setResultCacheDriver()` | `setResultCache()` |
| `QueryCacheProfile` | `getResultCacheDriver()` | `getResultCache()` |

# Upgrade to 3.1

## Deprecated schema- and namespace-related methods

The usage of the following schema- and namespace-related methods is deprecated:

- `AbstractPlatform::getListNamespacesSQL()`,
- `AbstractSchemaManager::listNamespaceNames()`,
- `AbstractSchemaManager::getPortableNamespacesList()`,
- `AbstractSchemaManager::getPortableNamespaceDefinition()`,
- `PostgreSQLSchemaManager::getSchemaNames()`.

Use `AbstractSchemaManager::listSchemaNames()` instead.

## `PostgreSQLSchemaManager` methods marked internal.

`PostgreSQLSchemaManager::getExistingSchemaSearchPaths()` and `::determineExistingSchemaSearchPaths()` have been marked internal.

## `OracleSchemaManager` methods marked internal.

`OracleSchemaManager::dropAutoincrement()` has been marked internal.

## Deprecated `AbstractPlatform::getReservedKeywordsClass()`

Instead of implementing `getReservedKeywordsClass()`, `AbstractPlatform` subclasses should implement
`createReservedKeywordsList()`.

## Deprecated `ReservedWordsCommand::setKeywordListClass()`

The usage of `ReservedWordsCommand::setKeywordListClass()` has been deprecated. To add or replace a keyword list,
use `setKeywordList()` instead.

## Deprecated `$driverOptions` argument of `PDO\Statement::bindParam()` and `PDO\SQLSrv\Statement::bindParam()`

The usage of the `$driverOptions` argument of `PDO\Statement::bindParam()` and `PDO\SQLSrv\Statement::bindParam()` is deprecated.
To define parameter binding type as `ASCII`, `BINARY` or `BLOB`, use the corresponding `ParameterType::*` constant.

## Deprecated `Connection::$_schemaManager` and `Connection::getSchemaManager()`

The usage of `Connection::$_schemaManager` and `Connection::getSchemaManager()` is deprecated.
Use `Connection::createSchemaManager()` instead.

## Deprecated `Connection::$_expr` and `Connection::getExpressionBuilder()`

The usage of `Connection::$_expr` and `Connection::getExpressionBuilder()` is deprecated.
Use `Connection::createExpressionBuilder()` instead.

## Deprecated `QueryBuilder::execute()`

The usage of `QueryBuilder::execute()` is deprecated. Use either `QueryBuilder::executeQuery()` or
`QueryBuilder::executeStatement()`, depending on whether the queryBuilder is a query (SELECT) or a statement (INSERT,
UPDATE, DELETE).

You might also consider the use of the new shortcut methods, such as:

- `fetchAllAssociative()`
- `fetchAllAssociativeIndexed()`
- `fetchAllKeyValue()`
- `fetchAllNumeric()`
- `fetchAssociative()`
- `fetchFirstColumn()`
- `fetchNumeric()`
- `fetchOne()`

# Upgrade to 3.0

## BC BREAK: leading colon in named parameter names not supported

The usage of the colon prefix when binding named parameters is no longer supported.

## BC BREAK `Doctrine\DBAL\Abstraction\Result` removed

The `Doctrine\DBAL\Abstraction\Result` interface is removed. Use the `Doctrine\DBAL\Result` class instead.

## BC BREAK: `Doctrine\DBAL\Types\Type::getDefaultLength()` removed

The `Doctrine\DBAL\Types\Type::getDefaultLength()` method has been removed as it served no purpose.

## BC BREAK: `Doctrine\DBAL\DBALException` class renamed

The `Doctrine\DBAL\DBALException` class has been renamed to `Doctrine\DBAL\Exception`.

## BC BREAK: `Doctrine\DBAL\Schema\Table` constructor new parameter

Deprecated parameter `$idGeneratorType` removed and added a new parameter `$uniqueConstraints`.
Constructor changed like so:

```diff
- __construct($name, array $columns = [], array $indexes = [], array $fkConstraints = [], $idGeneratorType = 0, array $options = [])
+ __construct($name, array $columns = [], array $indexes = [], array $uniqueConstraints = [], array $fkConstraints = [], array $options = [])
```

## BC BREAK: change in the behavior of `SchemaManager::dropDatabase()`

When dropping a database, the DBAL no longer attempts to kill the client sessions that use the database.
It's the responsibility of the operator to make sure that the database is not being used.

## BC BREAK: removed `Synchronizer` package

The `Doctrine\DBAL\Schema\Synchronizer\SchemaSynchronizer` interface and all its implementations have been removed.

## BC BREAK: removed wrapper `Connection` methods

The following methods of the `Connection` class have been removed:

1. `query()`.
2. `exec()`.
3. `executeUpdate()`.

## BC BREAK: Changes in the wrapper-level API ancestry

The wrapper-level `Connection` and `Statement` classes no longer implement the corresponding driver-level interfaces.

## BC BREAK: Removed `DBALException` factory methods

The following factory methods of the `DBALException` class have been removed:

1. `DBALException::invalidPlatformSpecified()`.
2. `DBALException::invalidPdoInstance()`.

## BC BREAK: PDO-based driver classes are moved under the `PDO` namespace

The following classes have been renamed:

- `PDOMySql\Driver` → `PDO\MySQL\Driver`
- `PDOOracle\Driver` → `PDO\OCI\Driver`
- `PDOPgSql\Driver` → `PDO\PgSQL\Driver`
- `PDOSqlite\Driver` → `PDO\SQLite\Driver`
- `PDOSqlsrv\Driver` → `PDO\SQLSrv\Driver`
- `PDOSqlsrv\Connection` → `PDO\SQLSrv\Connection`
- `PDOSqlsrv\Statement` → `PDO\SQLSrv\Statement`

## BC BREAK: Changes schema manager instantiation.

1. The `$platform` argument of all schema manager constructors is no longer optional.
2. A new `$platform` argument has been added to the `Driver::getSchemaManager()` method.

## BC BREAK: Changes in driver classes

1. All implementations of the `Driver` interface have been made final.
2. The `PDO\Connection` and `PDO\Statement` classes have been made final.
3. The `PDOSqlsrv\Connection` and `PDOSqlsrv\Statement` classes have been made final and no longer extend the corresponding PDO classes.
4. The `SQLSrv\LastInsertId` class has been made final.

## BC BREAK: Changes in wrapper-level exceptions

`DBALException::invalidTableName()` has been replaced with the `InvalidTableName` class.

## BC BREAK: Changes in driver-level exception handling

1. The `convertException()` method has been removed from the `Driver` interface. The logic of exception conversion has been moved to the `ExceptionConverter` interface. The drivers now must implement the `getExceptionConverter()` method.
2. The `driverException()` and `driverExceptionDuringQuery()` factory methods have been removed from the `DBALException` class.
3. Non-driver exceptions (e.g. exceptions of type `Error`) are no longer wrapped in a `DBALException`.

## BC BREAK: More driver-level methods are allowed to throw a `Driver\Exception`.

The following driver-level methods are allowed to throw a `Driver\Exception`:

- `Connection::prepare()`
- `Connection::lastInsertId()`
- `Connection::beginTransaction()`
- `Connection::commit()`
- `Connection::rollBack()`
- `ServerInfoAwareConnection::getServerVersion()`
- `Statement::bindParam()`
- `Statement::bindValue()`
- `Result::rowCount()`
- `Result::columnCount()`

The driver-level implementations of `Connection::query()` and `Connection::exec()` may no longer throw a `DBALException`.

## The `ExceptionConverterDriver` interface is removed

All drivers must implement the `convertException()` method which is now part of the `Driver` interface.

## The `PingableConnection` interface is removed

The functionality of pinging the server is no longer supported. Lost
connections are now automatically reconnected by Doctrine internally.

## BC BREAK: Deprecated driver-level classes and interfaces are removed.

- `AbstractDriverException`
- `DriverException`
- `PDOConnection`
- `PDOException`
- `PDOStatement`
- `IBMDB2\DB2Connection`
- `IBMDB2\DB2Driver`
- `IBMDB2\DB2Exception`
- `IBMDB2\DB2Statement`
- `Mysqli\MysqliConnection`
- `Mysqli\MysqliException`
- `Mysqli\MysqliStatement`
- `OCI8\OCI8Connection`
- `OCI8\OCI8Exception`
- `OCI8\OCI8Statement`
- `SQLSrv\SQLSrvConnection`
- `SQLSrv\SQLSrvException`
- `SQLSrv\SQLSrvStatement`

## BC BREAK: `ServerInfoAwareConnection::requiresQueryForServerVersion()` is removed.

The `ServerInfoAwareConnection::requiresQueryForServerVersion()` method has been removed as an implementation detail which is the same for all supported drivers.

## BC BREAK Changes in driver exceptions

1. The `Doctrine\DBAL\Driver\DriverException::getErrorCode()` method is removed. In order to obtain the driver error code, please use `::getCode()` or `::getSQLState()`.
2. The value returned by `Doctrine\DBAL\Driver\PDOException::getSQLState()` no longer falls back to the driver error code.

## BC BREAK: Changes in `OracleSchemaManager::createDatabase()`

The `$database` argument is no longer nullable or optional.

## BC BREAK: `Doctrine\DBAL\Types\Type::__toString()` removed

Relying on string representation was discouraged and has been removed.

## BC BREAK: Changes in the `Doctrine\DBAL\Schema` API

- Removed unused method `Doctrine\DBAL\Schema\AbstractSchemaManager::_getPortableFunctionsList()`
- Removed unused method `Doctrine\DBAL\Schema\AbstractSchemaManager::_getPortableFunctionDefinition()`
- Removed unused method `Doctrine\DBAL\Schema\OracleSchemaManager::_getPortableFunctionDefinition()`
- Removed unused method `Doctrine\DBAL\Schema\SqliteSchemaManager::_getPortableTableIndexDefinition()`

## BC BREAK: Removed support for DB-generated UUIDs

The support for DB-generated UUIDs was removed as non-portable.
Please generate UUIDs on the application side (e.g. using [ramsey/uuid](https://packagist.org/packages/ramsey/uuid)).

## BC BREAK: Changes in the `Doctrine\DBAL\Connection` API

- The following methods have been removed as leaking internal implementation details: `::getHost()`, `::getPort()`, `::getUsername()`, `::getPassword()`.

## BC BREAK: Changes in the `Doctrine\DBAL\Event` API

- `ConnectionEventArgs::getDriver()`, `::getDatabasePlatform()` and `::getSchemaManager()` methods have been removed. The connection information can be obtained from the connection which is available via `::getConnection()`.
- `SchemaColumnDefinitionEventArgs::getDatabasePlatform()` and `SchemaIndexDefinitionEventArgs::getDatabasePlatform()` have been removed for the same reason as above.

## BC BREAK: Changes in obtaining the currently selected database name

- The `Doctrine\DBAL\Driver::getDatabase()` method has been removed. Please use `Doctrine\DBAL\Connection::getDatabase()` instead.
- `Doctrine\DBAL\Connection::getDatabase()` will always return the name of the database currently connected to, regardless of the configuration parameters and will initialize a database connection if it's not yet established.
- A call to `Doctrine\DBAL\Connection::getDatabase()`, when connected to an SQLite database, will no longer return the database file path.

## BC BREAK: `Doctrine\DBAL\Driver::getName()` removed

The `Doctrine\DBAL\Driver::getName()` has been removed.

## BC BREAK Removed previously deprecated features

 * Removed `json_array` type and all associated hacks.
 * Removed `Connection::TRANSACTION_*` constants.
 * Removed `AbstractPlatform::DATE_INTERVAL_UNIT_*` and `AbstractPlatform::TRIM_*` constants.
 * Removed `AbstractPlatform::getSQLResultCasing()`, `::prefersSequences()` and `::supportsForeignKeyOnUpdate()` methods.
 * Removed `PostgreSqlPlatform::getDisallowDatabaseConnectionsSQL()` and `::getCloseActiveDatabaseConnectionsSQL()` methods.
 * Removed `MysqlSessionInit` listener.
 * Removed `MySQLPlatform::getCollationFieldDeclaration()`.
 * Removed `AbstractPlatform::getIdentityColumnNullInsertSQL()`.
 * Removed `AbstractPlatform::fixSchemaElementName()`.
 * Removed `Table::addUnnamedForeignKeyConstraint()` and `Table::addNamedForeignKeyConstraint()`.
 * Removed `Table::renameColumn()`.
 * Removed `SQLParserUtils::getPlaceholderPositions()`.
 * Removed `LoggerChain::addLogger`.
 * Removed `AbstractSchemaManager::getFilterSchemaAssetsExpression()`, `Configuration::getFilterSchemaAssetsExpression()`
   and `Configuration::getFilterSchemaAssetsExpression()`.
 * `SQLParserUtils::*_TOKEN` constants made private.

## BC BREAK changes the `Driver::connect()` signature

The method no longer accepts the `$username`, `$password` and `$driverOptions` arguments. The corresponding values are expected to be passed as the `"user"`, `"password"` and `"driver_options"` keys of the `$params` argument respectively.

## Removed `MasterSlaveConnection`

This class was deprecated in favor of `PrimaryReadReplicaConnection`

## BC BREAK: Changes in the portability layer

1. The platform-specific portability constants (`Portability\Connection::PORTABILITY_{PLATFORM}`) were internal implementation details which are no longer relevant.
2. The `Portability\Connection` class no longer extends the DBAL `Connection`.
3. The `Portability\Class` class has been made final.

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

## BC BREAK: Removed support for SQL Anywhere

The support for the SQL Anywhere database platform and the corresponding driver has been removed.

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

## BC BREAK: The `ServerInfoAwareConnection` interface now extends `Connection`

All implementations of the `ServerInfoAwareConnection` interface have to implement the methods defined in the `Connection` interface as well.

## BC BREAK: `VersionAwarePlatformDriver` interface now extends `Driver`

All implementations of the `VersionAwarePlatformDriver` interface have to implement the methods defined in the `Driver` interface as well.

## BC BREAK: Removed `MsSQLKeywords` class

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

## BC BREAK: Removed `Doctrine\DBAL\Version`

The `Doctrine\DBAL\Version` class is no longer available: please refrain from checking the DBAL version at runtime.

## BC BREAK User-provided `PDO` instance is no longer supported

In order to share the same `PDO` instances between DBAL and other components, initialize the connection in DBAL and access it using `Connection::getWrappedConnection()->getWrappedConnection()`.

## BC BREAK: the PDO symbols are no longer part of the DBAL API

1. The support of `PDO::PARAM_*`, `PDO::FETCH_*`, `PDO::CASE_*` and `PDO::PARAM_INPUT_OUTPUT` constants in the DBAL API is removed.
2. `\Doctrine\DBAL\Driver\PDOConnection` does not extend `\PDO` anymore. Please use `\Doctrine\DBAL\Driver\PDOConnection::getWrappedConnection()` to access the underlying `PDO` object.
3. `\Doctrine\DBAL\Driver\PDOStatement` does not extend `\PDOStatement` anymore.

Before:

```php
use Doctrine\DBAL\Portability\Connection;

$params = array(
    'wrapperClass' => Connection::class,
    'fetch_case' => PDO::CASE_LOWER,
);

$stmt->bindValue(1, 1, PDO::PARAM_INT);
$stmt->fetchAll(PDO::FETCH_COLUMN);
```

After:

```php
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
```

## BC BREAK: Removed Drizzle support

The Drizzle project is abandoned and is therefore not supported by Doctrine DBAL anymore.

## BC BREAK: Removed `dbal:import` CLI command

The `dbal:import` CLI command has been removed since it only worked with PDO-based drivers by relying on a non-documented behavior of the extension, and it was impossible to make it work with other drivers.
Please use other database client applications for import, e.g.:

 * For MySQL and MariaDB: `mysql [dbname] < data.sql`.
 * For PostgreSQL: `psql [dbname] < data.sql`.
 * For SQLite: `sqlite3 /path/to/file.db < data.sql`.

## BC BREAK: Changed signature of `ExceptionConverter::convert()`

Before:

```php
public function convert(string $message, Doctrine\DBAL\Driver\Exception $exception): DriverException
```

After:

```php
public function convert(Doctrine\DBAL\Driver\Exception $exception, ?Doctrine\DBAL\Query $query): DriverException
```

## BC Break: The `DriverException` constructor is now internal

The constructor of `Doctrine\DBAL\Exception\DriverException` is now `@internal`.

## BC Break: `Configuration`

- all `Configuration` methods are now typed
- `Configuration::setSchemaAssetsFilter()` now returns `void`
- `Configuration::$_attributes` has been removed; use individual properties in subclasses instead

# Upgrade to 2.13

## Deprecated SQLAnywhere drivers

The `SQLAnywhere` driver has been deprecated and will be removed from DBAL.

# Upgrade to 2.12

## Deprecated non-zero based positional parameter keys

The usage of one-based and other non-zero-based keys when binding positional parameters is deprecated.

It is recommended to not use any array keys so that the value of the parameter array complies with the [`list<>`](https://psalm.dev/docs/annotating_code/type_syntax/array_types/) type constraint.

```php
// This is valid (implicit zero-based parameter indexes)
$conn->fetchNumeric('SELECT ?, ?', [1, 2]);

// This is invalid (one-based parameter indexes)
$conn->fetchNumeric('SELECT ?, ?', [1 => 1, 2 => 2]);

// This is invalid (arbitrary parameter indexes)
$conn->fetchNumeric('SELECT ?, ?', [-31 => 1, 5 => 2]);

// This is invalid (non-sequential parameter indexes)
$conn->fetchNumeric('SELECT ?, ?', [0 => 1, 3 => 2]);
```

## Deprecated skipping prepared statement parameters

Some underlying drivers currently allow skipping prepared statement parameters. For instance:

```php
$conn->fetchOne('SELECT ?');
// NULL
```

This behavior should not be relied upon and may change in future versions.

## Deprecated colon prefix for prepared statement parameters

The usage of the colon prefix when binding named parameters is deprecated.

```php
$sql  = 'SELECT * FROM users WHERE name = :name OR username = :username';
$stmt = $conn->prepare($sql);

// The usage of the leading colon is deprecated
$stmt->bindValue(':name', $name);

// Only the parameter name should be passed
$stmt->bindValue('username', $username);

$stmt->execute();
```

## PDO signature changes with php 8

In php 8.0, the method signatures of two PDO classes which are extended by DBAL have changed. This affects the following classes:

* `Doctrine\DBAL\Driver\PDOConnection`
* `Doctrine\DBAL\Driver\PDOStatement`

Code that extends either of the classes needs to be adjusted in order to function properly on php 8. The updated method signatures are:

* `PDOConnection::query(?string $query = null, ?int $fetchMode = null, mixed ...$fetchModeArgs)`
* `PDOStatement::setFetchMode($mode, ...$args)`
* `PDOStatement::fetchAll($mode = null, ...$args)`

# Upgrade to 2.11

## Deprecated `Abstraction\Result`

The usage of the `Doctrine\DBAL\Abstraction\Result` interface is deprecated. In DBAL 3.0, the statement result at the wrapper level will be represented by the `Doctrine\DBAL\Result` class.

## Deprecated the functionality of dropping client connections when dropping a database

The corresponding `getDisallowDatabaseConnectionsSQL()` and `getCloseActiveDatabaseConnectionsSQL` methods
of the `PostgreSqlPlatform` class have been deprecated.

## Deprecated `Synchronizer` package

The `Doctrine\DBAL\Schema\Synchronizer\SchemaSynchronizer` interface and all its implementations are deprecated.

## Deprecated usage of wrapper-level components as implementations of driver-level interfaces

The usage of the wrapper `Connection` and `Statement` classes as implementations of the `Driver\Connection` and `Driver\Statement` interfaces is deprecated.

## Deprecations in the wrapper `Connection` class

1. The `executeUpdate()` method has been deprecated in favor of `executeStatement()`.
2. The `query()` method has been deprecated in favor of `executeQuery()`.
3. The `exec()` method has been deprecated in favor of `executeStatement()`.

Note that `PrimaryReplicaConnection::query()` ensures connection to the primary instance while `executeQuery()` doesn't.

Depending on the desired behavior:

- If the statement doesn't have to be executed on the primary instance, use `executeQuery()`.
- If the statement has to be executed on the primary instance and yields rows (e.g. `SELECT`), prepend `executeQuery()` with `ensureConnectedToPrimary()`.
- Otherwise, use `executeStatement()`.

## PDO-related classes outside of the PDO namespace are deprecated

The following PDO-related classes outside of the PDO namespace have been deprecated in favor of their counterparts in the PDO namespace:

- `PDOMySql\Driver` → `PDO\MySQL\Driver`
- `PDOOracle\Driver` → `PDO\OCI\Driver`
- `PDOPgSql\Driver` → `PDO\PgSQL\Driver`
- `PDOSqlite\Driver` → `PDO\SQLite\Driver`
- `PDOSqlsrv\Driver` → `PDO\SQLSrv\Driver`
- `PDOSqlsrv\Connection` → `PDO\SQLSrv\Connection`
- `PDOSqlsrv\Statement` → `PDO\SQLSrv\Statement`

## Deprecations in driver-level exception handling

1. The `ExceptionConverterDriver` interface and the usage of the `convertException()` method on the `Driver` objects are deprecated.
2. The `driverException()` and `driverExceptionDuringQuery()` factory methods of the `DBALException` class are deprecated.
3. Relying on the wrapper layer handling non-driver exceptions is deprecated.

## `DBALException` factory method deprecations

1. `DBALException::invalidPlatformType()` is deprecated as unused as of v2.7.0.
2. `DBALException::invalidPdoInstance()` as passing a PDO instance via configuration is deprecated.

## Deprecated `AbstractPlatform` methods.

1. `fixSchemaElementName()`.
2. `getSQLResultCasing()`.
3. `prefersSequences()`.
4. `supportsForeignKeyOnUpdate()`.

## `ServerInfoAwareConnection::requiresQueryForServerVersion()` is deprecated.

The `ServerInfoAwareConnection::requiresQueryForServerVersion()` method has been deprecated as an implementation detail which is the same for almost all supported drivers.

## Connection and Statement constructors are marked internal

1. Driver connection objects can be only created by the corresponding drivers.
2. Wrapper connection objects can be only created by the driver manager.
3. The driver and wrapper connection objects can be only created by the corresponding connection objects.

Additionally, the `SQLSrv\LastInsertId` class has been marked internal.

## The `PingableConnection` interface is deprecated

The wrapper connection will automatically handle the lost connection if the driver supports reporting it.

## `DriverException::getErrorCode()` is deprecated

The `DriverException::getErrorCode()` is deprecated as redundant and inconsistently supported by drivers. Use `::getCode()` or `::getSQLState()` instead.

## Non-interface driver methods have been marked internal

The non-interface methods of driver-level classes have been marked internal:

- `OCI8Connection::getExecuteMode()`
- `OCI8Statement::convertPositionalToNamedPlaceholders()`

## Deprecated `DBALException`

The `Doctrine\DBAL\DBALException` class has been deprecated in favor of `Doctrine\DBAL\Exception`.

## Inconsistently and ambiguously named driver-level classes are deprecated

The following classes under the `Driver` namespace have been deprecated in favor of their consistently named counterparts:

- `DriverException` → `Exception`
- `AbstractDriverException` → `AbstractException`
- `IBMDB2\DB2Driver` → `IBMDB2\Driver`
- `IBMDB2\DB2Connection` → `IBMDB2\Connection`
- `IBMDB2\DB2Statement` → `IBMDB2\Statement`
- `Mysqli\MysqliConnection` → `Mysqli\Connection`
- `Mysqli\MysqliStatement` → `Mysqli\Statement`
- `OCI8\OCI8Connection` → `OCI8\Connection`
- `OCI8\OCI8Statement` → `OCI8\Statement`
- `SQLSrv\SQLSrvConnection` → `SQLSrv\Connection`
- `SQLSrv\SQLSrvStatement` → `SQLSrv\Statement`
- `PDOConnection` → `PDO\Connection`
- `PDOStatement` → `PDO\Statement`

All driver-specific exception classes have been deprecated:

- `IBMDB2\DB2Exception`
- `Mysqli\MysqliException`
- `OCI8\OCI8Exception`
- `PDOException`
- `SQLSrv\SQLSrvException`

A driver-level exception should be only identified as a subtype of `Driver\Exception`.
Internal driver-level exception implementations may use `Driver\AbstractException` as the base class.
Driver-specific exception handling has to be implemented either in the driver or based on the type of the `Driver` implementation.

The `Driver\AbstractException` class has been marked internal.

## `Connection::getParams()` has been marked internal

Consumers of the Connection class should not rely on connection parameters stored in the connection object. If needed, they should be obtained from a different source, e.g. application configuration.

## Deprecated `Doctrine\DBAL\Driver::getDatabase()`

- The usage of `Doctrine\DBAL\Driver::getDatabase()` is deprecated. Please use `Doctrine\DBAL\Connection::getDatabase()` instead.
- The behavior of the SQLite connection returning the database file path as the database is deprecated and shouldn't be relied upon.

## Deprecated `Portability\Connection::PORTABILITY_{PLATFORM}` constants

The platform-specific portability mode flags are meant to be used only by the portability layer internally to optimize
the user-provided mode for the current database platform.

## Deprecated `MasterSlaveConnection` use `PrimaryReadReplicaConnection`

The `Doctrine\DBAL\Connections\MasterSlaveConnection` class is renamed to `Doctrine\DBAL\Connections\PrimaryReadReplicaConnection`.
In addition its configuration parameters `master`, `slaves` and `keepSlave` are renamed to `primary`, `replica` and `keepReplica`.

Before:

    $connection = DriverManager::getConnection(
        'wrapperClass' => 'Doctrine\DBAL\Connections\MasterSlaveConnection',
        'driver' => 'pdo_mysql',
        'master' => array('user' => '', 'password' => '', 'host' => '', 'dbname' => ''),
        'slaves' => array(
            array('user' => 'replica1', 'password', 'host' => '', 'dbname' => ''),
            array('user' => 'replica2', 'password', 'host' => '', 'dbname' => ''),
        ),
        'keepSlave' => true,
    ));
    $connection->connect('slave');
    $connection->connect('master');
    $connection->isConnectedToMaster();

After:

    $connection = DriverManager::getConnection(array(
        'wrapperClass' => 'Doctrine\DBAL\Connections\PrimaryReadReplicaConnection',
        'driver' => 'pdo_mysql',
        'primary' => array('user' => '', 'password' => '', 'host' => '', 'dbname' => ''),
        'replica' => array(
            array('user' => 'replica1', 'password', 'host' => '', 'dbname' => ''),
            array('user' => 'replica2', 'password', 'host' => '', 'dbname' => ''),
        )
        'keepReplica' => true,
    ));
    $connection->ensureConnectedToReplica();
    $connection->ensureConnectedToPrimary();
    $connection->isConnectedToPrimary();

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
