Note about upgrading: Doctrine uses static and runtime mechanisms to raise
awareness about deprecated code.

- Use of `@deprecated` docblock that is detected by IDEs (like PHPStorm) or
  Static Analysis tools (like Psalm, phpstan)
- Use of our low-overhead runtime deprecation API, details:
  https://github.com/doctrine/deprecations/

# Upgrade to 3.4

## Deprecated `AbstractPlatform` methods.

1. `usesSequenceEmulatedIdentityColumns()` and `getIdentitySequenceName()` have been deprecated since the fact of
   emulation of identity columns and the underlying sequence name are internal platform-specific implementation details.
2. `getDefaultSchemaName()` has been deprecated since it's not used to implement any of the portable APIs.
3. `supportsCreateDropDatabase()` has been deprecated. Try calling `AbstractSchemaManager::createDatabase`
    and/or `::dropDatabase()` to see if the corresponding operations are supported by the current database platform
    or implement conditional logic based on the platform class name.

## Deprecated `SqlitePlatform::getTinyIntTypeDeclarationSQL()` and `::getMediumIntTypeDeclarationSQL()` methods.

The methods have been deprecated since they are implemented only by the SQLite platform, and the column types
they implement are not portable across the rest of the supported platforms.

Use `SqlitePlatform::getSmallIntTypeDeclarationSQL()` and `::getIntegerTypeDeclarationSQL()` respectively instead.

## Deprecated `NULL` schema asset filter.

Not passing an argument to `Configuration::setSchemaAssetsFilter()` and passing `NULL` as the value of `$callable`
has been deprecated. In order to disable filtering, pass a callable that always returns true.

## Deprecated custom schema options.

Custom schema options have been deprecated since they effectively duplicate the functionality of platform options.

The following `Column` class properties and methods have been deprecated:

- `$_customSchemaOptions`,
- `setCustomSchemaOption()`,
- `hasCustomSchemaOption()`,
- `getCustomSchemaOption()`,
- `setCustomSchemaOptions()`,
- `getCustomSchemaOptions()`.

Use platform options instead.

## Deprecated `array` and `object` column types.

The `array` and `object` column types have been deprecated since they use PHP built-in serialization. Without additional
configuration, which the API of these types doesn't allow, the usage of built-in serialization may lead to
security issues.

The following classes and constants have been deprecated:
- `ArrayType`,
- `ObjectType`,
- `Types::ARRAY`,
- `Types::OBJECT`.

Use JSON for storing unstructured data.

## Deprecated `Driver::getSchemaManager()`.

The `Driver::getSchemaManager()` method has been deprecated. Use `AbstractPlatform::createSchemaManager()` instead.

## Deprecated `ConsolerRunner`.

The `ConsoleRunner` class has been deprecated. Use Symfony Console documentation
to bootstrap a command-line application.

## Deprecated `Visitor` interfaces and `visit()` methods on schema objects.

The following interfaces and classes have been deprecated:

1. `Visitor`,
2. `NamespaceVisitor`,
3. `AbstractVisitor`.

The following methods have been deprecated:

1. `Schema::visit()`,
2. `Table::visit()`,
3. `Sequence::visit()`.

Instead of having schema objects call the visitor API, call the API of the schema objects.

## Deprecated removal of namespaced assets from schema.

The `RemoveNamespacedAssets` schema visitor and the usage of namespaced database object names with the platforms
that don't support them have been deprecated.

## Deprecated the functionality of checking schema for the usage of reserved keywords.

The following components have been deprecated:

1. The `dbal:reserved-words` console command.
2. The `ReservedWordsCommand` and `ReservedKeywordsValidator` classes.
3. The `KeywordList::getName()` method.

Use the documentation on the used database platform(s) instead.

## Deprecated `CreateSchemaSqlCollector` and `DropSchemaSqlCollector`.

The `CreateSchemaSqlCollector` and `DropSchemaSqlCollector` classes have been deprecated in favor of
`CreateSchemaObjectsSQLBuilder` and `DropSchemaObjectsSQLBuilder` respectively.

## Deprecated calling `AbstractPlatform::getCreateTableSQL()` with any of the `CREATE_INDEXES` and `CREATE_FOREIGNKEYS`
flags unset.

Not setting the `CREATE_FOREIGNKEYS` flag and unsetting the `CREATE_INDEXES` flag when calling
`AbstractPlatform::getCreateTableSQL()` has been deprecated. The table should be always created with indexes.
In order to build the statements that create multiple tables referencing each other via foreign keys,
use `AbstractPlatform::getCreateTablesSQL()`.

## Deprecated `AbstractPlatform::supportsForeignKeyConstraints()`.

The `AbstractPlatform::supportsForeignKeyConstraints()` method has been deprecated. All platforms should support
foreign key constraints.

## Deprecated `AbstractPlatform::supportsForeignKeyConstraints()`.

Relying on the DBAL not generating DDL for foreign keys on MySQL engines other than InnoDB is deprecated.
Define foreign key constraints only if they are necessary.

## Deprecated `AbstractPlatform` methods exposing quote characters.

The `AbstractPlatform::getStringLiteralQuoteCharacter()` and `::getIdentifierQuoteCharacter()` methods
have been deprecated. Use `::quoteStringLiteral()` and `::quoteIdentifier()` to quote string literals and identifiers
respectively.

## Deprecated `AbstractSchemaManager::getDatabasePlatform()`

The `AbstractSchemaManager::getDatabasePlatform()` method has been deprecated. Use `Connection::getDatabasePlatform()`
instead.

## Deprecated passing date interval parameters as integer.

Passing date interval parameters to the following `AbstractPlatform` methods as integer has been deprecated:

- the `$seconds` argument in `::getDateAddSecondsExpression()`,
- the `$seconds` parameter in `::getDateSubSecondsExpression()`,
- the `$minutes` parameter in `::getDateAddMinutesExpression()`,
- the `$minutes` parameter in `::getDateSubMinutesExpression()`,
- the `$hours` parameter in `::getDateAddHourExpression()`,
- the `$hours` parameter in `::getDateAddHourExpression()`,
- the `$days` parameter in `::getDateAddDaysExpression()`,
- the `$days` parameter in `::getDateSubDaysExpression()`,
- the `$weeks` parameter in `::getDateAddWeeksExpression()`,
- the `$weeks` parameter in `::getDateSubWeeksExpression()`,
- the `$months` parameter in `::getDateAddMonthExpression()`,
- the `$months` parameter in `::getDateSubMonthExpression()`,
- the `$quarters` parameter in `::getDateAddQuartersExpression()`,
- the `$quarters` parameter in `::getDateSubQuartersExpression()`,
- the `$years` parameter in `::getDateAddYearsExpression()`,
- the `$years` parameter in `::getDateSubYearsExpression()`.

Use the strings representing numeric SQL literals instead (e.g. `'1'` instead of `1`).

## Deprecated transaction nesting without savepoints

Starting a transaction inside another transaction with
`Doctrine\DBAL\Connection::beginTransaction()` without enabling transaction
nesting with savepoints beforehand is deprecated.

Transaction nesting with savepoints can be enabled with
`$connection->setNestTransactionsWithSavepoints(true);`

In case your platform does not support savepoints, you will have to rework your
application logic so as to avoid nested transaction blocks.

## Added runtime deprecations for the default string column length.

In addition to the formal deprecation introduced in DBAL 3.2, the library will now emit a deprecation message at runtime
if the string or binary column length is omitted, but it's required by the target database platform.

## Deprecated `AbstractPlatform::getVarcharTypeDeclarationSQL()`

The `AbstractPlatform::getVarcharTypeDeclarationSQL()` method has been deprecated.
Use `AbstractPlatform::getStringTypeDeclarationSQL()` instead.

## Deprecated `$database` parameter of `AbstractSchemaManager::list*()` methods

Passing `$database` to the following methods has been deprecated:

- `AbstractSchemaManager::listSequences()`,
- `AbstractSchemaManager::listTableColumns()`,
- `AbstractSchemaManager::listTableForeignKeys()`.

Only introspection of the current database will be supported in DBAL 4.0.

## Deprecated `AbstractPlatform` schema introspection methods

The following schema introspection methods have been deprecated:

- `AbstractPlatform::getListTablesSQL()`,
- `AbstractPlatform::getListTableColumnsSQL()`,
- `AbstractPlatform::getListTableIndexesSQL()`,
- `AbstractPlatform::getListTableForeignKeysSQL()`.

The queries used for schema introspection are an internal implementation detail of the DBAL.

## Deprecated `collate` option for MySQL

This undocumented option is deprecated in favor of `collation`.

## Deprecated `AbstractPlatform::getListTableConstraintsSQL()`

This method is unused by the DBAL since 2.0.

## Deprecated `Type::getName()`

This will method is not useful for the DBAL anymore, and will be removed in 4.0.
As a consequence, depending on the name of a type being `json` for `jsonb` to
be used for the Postgres platform is deprecated in favor of extending
`Doctrine\DBAL\Types\JsonType`.

## Deprecated `AbstractPlatform::getColumnComment()`, `AbstractPlatform::getDoctrineTypeComment()`,
`AbstractPlatform::hasNativeJsonType()` and `Type::requiresSQLCommentHint()`

DBAL no longer needs column comments to ensure proper diffing. Note that all the
methods should probably have been marked as internal as these comments were an
implementation detail of the DBAL.

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

The `Configuration` methods `getSQLLogger()` and `setSQLLogger()` have been deprecated as well.

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
