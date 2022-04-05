Note about upgrading: Doctrine uses static and runtime mechanisms to raise
awareness about deprecated code.

- Use of `@deprecated` docblock that is detected by IDEs (like PHPStorm) or
  Static Analysis tools (like Psalm, phpstan)
- Use of our low-overhead runtime deprecation API, details:
  https://github.com/doctrine/deprecations/

# Upgrade to 4.0

## Removed the `doctrine-dbal` binary.

The documentation explains how the console tools can be bootstrapped for standalone usage.

The method `ConsoleRunner::printCliConfigTemplate()` has been removed as well because it was only useful in the context
of the `doctrine-dbal` binary.

## Removed support for the `$database` parameter of `AbstractSchemaManager::list*()` methods

Passing `$database` to the following methods is no longer supported:

- `AbstractSchemaManager::listSequences()`,
- `AbstractSchemaManager::listTableColumns()`,
- `AbstractSchemaManager::listTableForeignKeys()`.

## Removed `AbstractPlatform` schema introspection methods

The following schema introspection methods have been removed:

- `AbstractPlatform::getListTableColumnsSQL()`,
- `AbstractPlatform::getListTableIndexesSQL()`,
- `AbstractPlatform::getListTableForeignKeysSQL()`,
- `AbstractPlatform::getListTableConstraintsSQL()`.

## Abstract methods in the `AbstractSchemaManager` class have been declared as `abstract`

The following abstract methods in the `AbstractSchemaManager` class have been declared as `abstract`:

- `selectDatabaseColumns()`,
- `selectDatabaseIndexes()`,
- `selectDatabaseForeignKeys()`,
- `getDatabaseTableOptions()`.

Every non-abstract schema manager class must implement them in order to satisfy the API.

# BC Break: The number of affected rows is returned as `int|string`

The signatures of the methods returning the number of affected rows changed as returning `int|string` instead of `int`.

# BC Break: Dropped support for `collate` option for MySQL

Use `collation` instead.

## BC BREAK: Removed `Type::getName()`

As a consequence, only types extending `JsonType` or that type itself can have
the `jsonb` platform option set.

## BC BREAK: Deployed database schema no longer contains the information about abstract data types

Database column comments no longer contain type comments added by DBAL.
If you use `doctrine/migrations`, it should generate a migration dropping those
comments from all columns that have them.
As a consequence, introspecting a table no longer guarantees getting the same
column types that were used when creating that table.

## BC BREAK: Removed `AbstractPlatform::prefersIdentityColumns()`

The `AbstractPlatform::prefersIdentityColumns()` method has been removed.

## BC BREAK: Removed the `Graphviz` visitor.

The `Doctrine\DBAL\Schema\Visitor\Graphviz` class has been removed.

## BC BREAK: Removed support for Oracle 12c (12.2.0.1) and older

Oracle 12c (12.2.0.1) and older are not supported anymore.

## BC BREAK: Removed support for MariaDB 10.2.6 and older

MariaDB 10.2.6 and older are not supported anymore. The following classes have been removed:

* `Doctrine\DBAL\Platforms\MariaDb1027Platform`
* `Doctrine\DBAL\Platforms\Keywords\MariaDb102Keywords`

## BC BREAK: Removed support for MySQL 5.6 and older

MySQL 5.6 and older are not supported anymore. The following classes have been merged into their respective
parent classes:

* `Doctrine\DBAL\Platforms\MySQL57Platform`
* `Doctrine\DBAL\Platforms\Keywords\MySQL57Keywords`

## BC BREAK: Removed active support for Postgres 9

Postgres 9 is not actively supported anymore. The following classes have been merged into their respective parent class:

* `Doctrine\DBAL\Platforms\PostgreSQL100Platform`
* `Doctrine\DBAL\Platforms\Keywords\PostgreSQL100Keywords`

## BC BREAK: Removed Platform "commented type" API

Since `Type::requiresSQLCommentTypeHint()` already allows determining whether a
type should result in SQL columns with a type hint in their comments, the
following methods are removed:

- `AbstractPlatform::isCommentedDoctrineType()`
- `AbstractPlatform::initializeCommentedDoctrineTypes()`
- `AbstractPlatform::markDoctrineTypeCommented()`

The protected property `AbstractPlatform::$doctrineTypeComments` is removed as
well.

## BC BREAK: Removed `Type::canRequireSQLConversion()`

The `Type::canRequireSQLConversion()` method has been removed.

## BC BREAK: Removed `Connection::getWrappedConnection()`, `Connection::connect()` made `protected`.

The wrapper-level `Connection::getWrappedConnection()` method has been removed. The `Connection::connect()` method
has been made `protected` and now must return the underlying driver-level connection.

## BC BREAK: Added `getNativeConnection()` to driver connections and removed old accessors 

Driver and middleware connections must implement `getNativeConnection()` now. This new method replaces several accessors
that have been removed:

* `Doctrine\DBAL\Driver\PDO\Connection::getWrappedConnection()`
* `Doctrine\DBAL\Driver\PDO\SQLSrv\Connection::getWrappedConnection()`
* `Doctrine\DBAL\Driver\Mysqli\Connection::getWrappedResourceHandle()`

## BC BREAK: Removed `SQLLogger` and its implementations.

The `SQLLogger` interface and its implementations `DebugStack` and `LoggerChain` have been removed.
The corresponding `Configuration` methods, `getSQLLogger()` and `setSQLLogger()`, have been removed as well.

## BC BREAK: Removed `SqliteSchemaManager::createDatabase()` and `dropDatabase()` methods.

The `SqliteSchemaManager::createDatabase()` and `dropDatabase()` methods have been removed.

## BC BREAK: Removed `AbstractSchemaManager::dropAndCreate*()` and `::tryMethod()` methods.

The following `AbstractSchemaManager` methods have been removed:

1. `AbstractSchemaManager::dropAndCreateConstraint()`,
6. `AbstractSchemaManager::dropAndCreateDatabase()`,
3. `AbstractSchemaManager::dropAndCreateForeignKey()`,
2. `AbstractSchemaManager::dropAndCreateIndex()`,
4. `AbstractSchemaManager::dropAndCreateSequence()`,
5. `AbstractSchemaManager::dropAndCreateTable()`,
7. `AbstractSchemaManager::dropAndCreateView()`,
8. `AbstractSchemaManager::tryMethod()`.

## BC BREAK: Removed support for SQL Server 2016 and older

DBAL is now tested only with SQL Server 2017 and newer.

## BC BREAK: `Statement::execute()` marked private.

The `Statement::execute()` method has been marked private.

## BC BREAK: Removed `QueryBuilder::execute()`.

The `QueryBuilder::execute()` method has been removed.

## BC BREAK: Removed the `Constraint` interface.

The `Constraint` interface has been removed. The `ForeignKeyConstraint`, `Index` and `UniqueConstraint` classes
no longer implement this interface.

The following methods that used to accept an instance of `Constraint` have been removed:

- `AbstractPlatform::getCreateConstraintSQL()`,
- `AbstractSchemaManager::createConstraint()`, `::dropConstraint()` and `::dropAndCreateConstraint()`,
- `ForeignKeyConstraint::getColumns()` and `::getQuotedColumns()`.

## BC BREAK: Removed `AbstractSchemaManager::getSchemaSearchPaths()`.

The `AbstractSchemaManager::getSchemaSearchPaths()` method has been removed.

The schema configuration returned by `AbstractSchemaManager::createSchemaConfig()` will contain a non-empty schema name
only for those database platforms that support schemas (currently, PostgreSQL).

The schema returned by `AbstractSchemaManager::createSchema()` will have a non-empty name only for those
database platforms that support schemas.

## BC BREAK: Removed `AbstractAsset::getFullQualifiedName()`.

The `AbstractAsset::getFullQualifiedName()` method has been removed.

## BC BREAK: Removed schema methods related to explicit foreign key indexes.

The following methods have been removed:

- `Schema::hasExplicitForeignKeyIndexes()`,
- `SchemaConfig::hasExplicitForeignKeyIndexes()`,
- `SchemaConfig::setExplicitForeignKeyIndexes()`.

## BC BREAK: Removed `Schema::getTableNames()`.

The `Schema::getTableNames()` method has been removed.

## BC BREAK: Changes in `Schema` method return values.

The `Schema::getNamespaces()`, `Schema::getTables()` and `Schema::getSequences()` methods will return numeric arrays
of namespaces, tables and sequences respectively instead of associative arrays.

## BC BREAK: Removed `SqlitePlatform::udf*()` methods.

The following `SqlitePlatform` methods have been removed:

- `udfSqrt()`,
- `udfMod()`,
- `udfLocate()`.

## BC BREAK: `SQLServerPlatform` methods marked protected.

The following `SQLServerPlatform` methods have been marked protected:

- `getDefaultConstraintDeclarationSQL()`,
- `getAddExtendedPropertySQL()`,
- `getDropExtendedPropertySQL()`,
- `getUpdateExtendedPropertySQL()`.

## BC BREAK: `OraclePlatform` methods marked protected.

The `OraclePlatform::getCreateAutoincrementSql()` method has been marked protected.

## BC BREAK: Removed `OraclePlatform::assertValidIdentifier()`.

The `OraclePlatform::assertValidIdentifier()` method has been removed.

## BC BREAK: Changed signatures of `AbstractPlatform::getIndexDeclarationSQL()` and `::getUniqueConstraintDeclarationSQL()`

The `AbstractPlatform::getIndexDeclarationSQL()` and `::getUniqueConstraintDeclarationSQL()` methods no longer accept
the name of the object as a separate parameter. The name of the passed index or constraint is used instead.

## BC BREAK: Removed `AbstractPlatform::canEmulateSchemas()`

The `AbstractPlatform::canEmulateSchemas()` method and the schema emulation implemented in the SQLite platform
have been removed.

## BC BREAK: The `$fromColumn` parameter of the `ColumnDiff` constructor made required

## BC BREAK: Changes in the return value of `Table::getColumns()`

1. The columns are returned as a list, not as an associative array.
2. The columns are no longer sorted based on whether they belong to the primary key or a foreign key.

## BC BREAK: Removed schema comparison APIs that don't account for the current database connection and the database platform

The `Schema::getMigrateFromSql()` and `::getMigrateToSql()` methods have been removed.

## BC BREAK: Removed driver-level APIs that don't take the server version into account.

The `ServerInfoAwareConnection` interface has been removed. The `getServerVersion()` method has been made
part of the driver-level `Connection` interface.

The `VersionAwarePlatformDriver` interface has been removed. The `Driver::getDatabasePlatform()` method now accepts
a `ServerVersionProvider` argument that will provide the server version, if the driver relies on the server version
to instantiate a database platform.

## BC BREAK: Removed `AbstractPlatform::getName()`

The `AbstractPlatform::getName()` method has been removed.

## BC BREAK: Removed versioned platform classes that represent the lowest supported version.

The following platform-related classes have been removed:

1. `PostgreSQL94Platform` and `PostgreSQL94Keywords`.
2. `SQLServer2012Platform` and `SQLServer2012Keywords`.

## BC BREAK: Removed `AbstractPlatform::getNowExpression()`.

The `AbstractPlatform::getNowExpression()` method has been removed.

## BC BREAK: Removed reference from `ForeignKeyConstraint` to its local (referencing) `Table`.

Reference from `ForeignKeyConstraint` to its local (referencing) `Table` is removed as well as the following methods:

- `setLocalTable()`,
- `getLocalTable()`,
- `getLocalTableName()`.

The type of `SchemaDiff::$orphanedForeignKeys` has changed from list of foreign keys (list<ForeignKeyConstraint>)
to map of referencing tables to their list of foreign keys (array<string,list<ForeignKeyConstraint>>).

## BC BREAK: Removed redundant `AbstractPlatform` methods.

The following redundant `AbstractPlatform` methods have been removed:

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
- `getUniqueFieldDeclarationSQL()`,
- `getListUsersSQL()`,
- `supportsIndexes()`,
- `supportsAlterTable()`,
- `supportsTransactions()`,
- `supportsPrimaryConstraints()`,
- `supportsViews()`,
- `supportsLimitOffset()`.
- `supportsGettingAffectedRows()`.

## Abstract methods in the `AbstractPlatform` class have been declared as `abstract`.

The following abstract methods in the `AbstractPlatform` class have been declared as `abstract`:

- `getListTablesSQL()`,
- `getAlterTableSQL()`,
- `getListTableColumnsSQL()`,
- `getListTableIndexesSQL()`,
- `getListTableForeignKeysSQL()`,
- `getCreateViewSQL()`,
- `getListViewsSQL()`,
- `getDropViewSQL()`,
- `getDateArithmeticIntervalExpression()`,
- `getDateDiffExpression()`,
- `getTimeTypeDeclarationSQL()`,
- `getDateTimeTypeDeclarationSQL()`,
- `getLocateExpression()`,
- `getSetTransactionIsolationSQL()`.

Every non-abstract platform class must implement them in order to satisfy the API.

## `Connection::lastInsertId()` throws an exception when there's no identity value.

Instead of returning an empty value, `Connection::lastInsertId()` throws an exception when there's no identity value.

## Removed static keyword from `Comparator::compareSchemas()` signature

The method `Comparator::compareSchemas()` cannot be called statically anymore.

## Removed `Comparator::compare()`

The method `Comparator::compare()` has been removed, use `Comparator::compareSchemas()` instead.

## Removed `TableGenerator` component

The `TableGenerator` component has been removed.

## Removed support for `Connection::lastInsertId($name)`

The `Connection::lastInsertId()` method no longer accepts a sequence name.

## Removed defaults for MySQL table charset, collation and engine

The library no longer provides the default values for MySQL table charset, collation and engine.
If omitted in the table definition, MySQL will derive the values from the database options.

## Removed `ReservedWordsCommand::setKeywordListClass()`

To add or replace a keyword list, use `ReservedWordsCommand::setKeywordList()`.

## Removed `AbstractPlatform::getReservedKeywordsClass()`

Instead of implementing `AbstractPlatform::getReservedKeywordsClass()`, platforms must implement `AbstractPlatform::createReservedKeywordsList()`. The latter has been made abstract. 

## `PostgreSQLSchemaManager` methods have been made protected.

`PostgreSQLSchemaManager::getExistingSchemaSearchPaths()` and `::determineExistingSchemaSearchPaths()` have been made protected.
The former has also been made final.

## Removed schema- and namespace-related methods

The following schema- and namespace-related methods have been removed:

- `AbstractPlatform::getListNamespacesSQL()`,
- `AbstractSchemaManager::listNamespaceNames()`,
- `AbstractSchemaManager::getPortableNamespacesList()`,
- `AbstractSchemaManager::getPortableNamespaceDefinition()`,
- `PostgreSQLSchemaManager::getSchemaNames()`.

## Removed the `$driverOptions` argument of `PDO\Statement::bindParam()` and `PDO\SQLSrv\Statement::bindParam()`

The `$driverOptions` argument of `PDO\Statement::bindParam()` and `PDO\SQLSrv\Statement::bindParam()` has been removed. The specifics of binding a parameter to the statement should be specified using the `$type` argument.

## BC BREAK: Removed `Connection::$_schemaManager` and `Connection::getSchemaManager()`

The `Connection` and `AbstractSchemaManager` classes used to have a reference on each other effectively making a circular reference. Use `createSchemaManager()` to instantiate a schema manager.

## BC BREAK: Removed `Connection::$_expr` and `Connection::getExpressionBuilder()`

The `Connection` and `ExpressionBuilder` classes used to have a reference on each other effectively making a circular reference. Use `createExpressionBuilder()` to instantiate an expression builder.

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

## BC BREAK: Changes to handling binary fields

- Binary fields whose length exceeds the maximum field size on a given platform are no longer represented as `BLOB`s.
  Use binary fields of a size which fits all target platforms, or use blob explicitly instead.
- Binary fields are no longer represented as streams in PHP. They are represented as strings.

## BC BREAK: Removal of Doctrine Cache

The following methods have been removed.

| class               | method                   | replacement        |
| ------------------- | ------------------------ | ------------------ |
| `Configuration`     | `setResultCacheImpl()`   | `setResultCache()` |
| `Configuration`     | `getResultCacheImpl()`   | `getResultCache()` |
| `QueryCacheProfile` | `setResultCacheDriver()` | `setResultCache()` |
| `QueryCacheProfile` | `getResultCacheDriver()` | `getResultCache()` |

# Upgrade to 3.4

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

## Deprecated `AbstractPlatform::getColumnComment()` and `AbstractPlatform::getDoctrineTypeComment()`

DBAL no longer needs column comments to ensure proper diffing. Note that both
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
