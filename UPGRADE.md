Note about upgrading: Doctrine uses static and runtime mechanisms to raise
awareness about deprecated code.

- Use of `@deprecated` docblock that is detected by IDEs (like PHPStorm) or
  Static Analysis tools (like Psalm, phpstan)
- Use of our low-overhead runtime deprecation API, details:
  https://github.com/doctrine/deprecations/

# Upgrade to 4.0

## BC BREAK: removed `AbstractMySQLPlatform` methods.

1. `getColumnTypeSQLSnippets()`,
2. `getDatabaseNameSQL()`.

## BC BREAK: Removed lock-related `AbstractPlatform` methods

The methods `AbstractPlatform::getReadLockSQL()`, `::getWriteLockSQL()` and `::getForUpdateSQL()` have been removed
Use `QueryBuilder::forUpdate()` as a replacement for the latter.

## BC BREAK: BIGINT values are cast to int if possible

`BigIntType` casts values retrieved from the database to int if they're inside
the integer range of PHP. Previously, those values were always cast to string.

## BC BREAK: Stricter `DateTime` types

The following types don't accept or return `DateTimeImmutable` instances anymore:

* `DateTimeType`
* `DateTimeTzType`
* `DateType`
* `TimeType`
* `VarDateTimeType`

As a consequence, the following type classes don't extend their mutable
counterparts anymore:

* `DateTimeImmutableType`
* `DateTimeTzImmutableType`
* `DateImmutableType`
* `TimeImmutableType`
* `VarDateTimeImmutableType`

## BC BREAK: Remove legacy execute and fetch methods.

The following methods have been removed:

* `Result::fetch()`
* `Result::fetchAll()`
* `Connection::exec()`
* `Connection::executeUpdate()`
* `Connection::query()`

Additionally, the `FetchMode` class has been removed.

## BC BREAK: Removed the `url` connection parameter

DBAL ships with a new and configurable DSN parser that can be used to parse a
database URL into connection parameters understood by `DriverManager`.

### Before

```php
$connection = DriverManager::getConnection(
    ['url' => 'mysql://my-user:t0ps3cr3t@my-host/my-database']
);
```

### After

```php
$dsnParser  = new DsnParser(['mysql' => 'pdo_mysql']);
$connection = DriverManager::getConnection(
    $dsnParser->parse('mysql://my-user:t0ps3cr3t@my-host/my-database')
);
```

## BC BREAK: Removed `Connection::PARAM_*_ARRAY` constants

Use the enum `ArrayParameterType` instead.

## BC BREAK: Disallowed partial version numbers in ``serverVersion``

The ``serverVersion`` connection parameter must consist of 3 numbers:

```diff
-'serverVersion' => '8.0'
+'serverVersion' => '8.0.31'
```

## BC BREAK: Removed `mariadb-` prefix hack

Previously, it was necessary to prefix the `serverVersion` parameter with
`mariadb-` when using MariaDB. Doing so is now considered invalid, and you
should prefer using the version as returned by `SELECT VERSION();`

```diff
-'serverVersion' => 'mariadb-10.9.3'
+'serverVersion' => '10.9.3-MariaDB-1'
```


## BC BREAK: Removed `SchemaDiff::$orphanedForeignKeys`

The functionality of automatically dropping the foreign keys referencing the tables being dropped has been removed.

## BC BREAK: Removed registration of user defined functions for SQLite

DBAL does not register functions for SQLite anymore. The following functions
which were previously provided by DBAL have been removed:

* `locate()`: SQLite provides the function `instr()` that behaves similarly.
  Use `AbstractPlatform::getLocateExpression()` if you need a portable solution.
* `mod()`: SQLite provides a `%` operator for modulo calculations.
  Use `AbstractPlatform::getModExpression()` if you need a portable solution.
  Since version 3.35.0 SQLite also provides a `mod()` function if math
  functions have been enabled.
* `sqrt()`: Upgrade to SQLite 3.35.0 and compile SQLite with math functions to
  get a native `sqrt()` function. If you need a `sqrt()` implementation for an
  earlier release of SQLite, you can polyfill it.

  ```php
  // pdo_sqlite driver
  $connection->getNativeConnection()
      ->sqliteCreateFunction('sqrt', \sqrt(...), 1);

  // sqlite3 driver
  $connection->getNativeConnection()
      ->createFunction('sqrt', \sqrt(...), 1);
  ```

The `userDefinedFunctions` driver option has also been removed. If you want
to register your own functions, do so by calling `sqliteCreateFunction()`
or `createFunction()` on the PDO or SQLite3 connection.

## BC BREAK: Removed `Table` methods

The following `Table` methods have been removed:

- `changeColumn()`,
- `getForeignKeyColumns()`,
- `getPrimaryKeyColumns()`,
- `hasPrimaryKey()`.

## BC BREAK: removed `SchemaException` error code constants

The following `SchemaException` class constants have been removed:

- `TABLE_DOESNT_EXIST`,
- `TABLE_ALREADY_EXISTS`,
- `COLUMN_DOESNT_EXIST`,
- `COLUMN_ALREADY_EXISTS`,
- `INDEX_DOESNT_EXIST`,
- `INDEX_ALREADY_EXISTS`,
- `SEQUENCE_DOENST_EXIST`,
- `SEQUENCE_ALREADY_EXISTS`,
- `INDEX_INVALID_NAME`,
- `FOREIGNKEY_DOESNT_EXIST`,
- `CONSTRAINT_DOESNT_EXIST`,
- `NAMESPACE_ALREADY_EXISTS`.

## BC BREAK: Exception classes have been converted to interfaces

The `Doctrine\DBAL\Exception` and the `Doctrine\DBAL\Schema\SchemaException` classes are now interfaces.

## BC BREAK: removed misspelled isFullfilledBy() method

This method's name was spelled incorrectly. Use `isFulfilledBy` instead.

## BC BREAK: removed default PostgreSQL connection database.

When connecting to a PostgreSQL server, the driver will no longer connect to the "postgres" database by default.

## BC BREAK: removed support for the "default_dbname" parameter of the wrapper `Connection`.

The "default_dbname" parameter of the wrapper `Connection` is no longer supported.

## BC BREAK: removed fallback connection used to determine the database platform.

When determining the database platform, if an attempt to connect using the provided configuration fails,
the wrapper connection will no longer fall back to a configuration without the database name.

## BC BREAK: removed support for driver name aliases.

Driver name aliases are no longer supported.

## BC BREAK: removed support for the "platform" parameter of the wrapper `Connection`.

The support for the "platform" parameter of the wrapper `Connection` has been removed.

## BC BREAK: removed support for "unique" and "check" column properties.

The "unique" and "check" column properties are no longer supported.

## BC BREAK: removed default precision and scale of decimal columns.

The DBAL no longer provides default values for precision and scale of decimal columns.

## BC BREAK: a non-empty WHERE clause is not enforced in data manipulation `Connection` methods.

The `Connection::update()` and `::delete()` methods no longer enforce a non-empty WHERE clause. If modification
of all table rows should not be allowed, it should be implemented in the application code.

## BC BREAK: removed wrapper- and driver-level `Statement::bindParam()` methods.

The following methods have been removed:

1. `Doctrine\DBAL\Statement::bindParam()`,
2. `Doctrine\DBAL\Driver\Statement::bindParam()`.

## BC BREAK: made parameter type in driver-level `Statement::bindValue()` required.

The `$type` parameter of the driver-level `Statement::bindValue()` has been made required.

## BC BREAK: removed support for using NULL as prepared statement parameter type.

The value of parameter type used in the wrapper layer (e.g. in `Connection::executeQuery()`
or `Statement::bindValue()`) can no longer be `NULL`.

## BC BREAK: converted enum-like classes to enums

The following classes have been converted to enums:

1. `Doctrine\DBAL\ColumnCase`,
2. `Doctrine\DBAL\LockMode`,
3. `Doctrine\DBAL\ParameterType`,
4. `Doctrine\DBAL\ArrayParameterType`,
5. `Doctrine\DBAL\TransactionIsolationLevel`,
6. `Doctrine\DBAL\Platforms\DateIntervalUnit`,
7. `Doctrine\DBAL\Platforms\TrimMode`.
8. `Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode`

The corresponding class constants are now instances of their enum type.

## BC BREAK: dropped naming convention for default constraints on SQL Server

The DBAL no longer generates default constraint names using the table name and column name. The name is now generated
by the database.

## BC BREAK: renamed SQLite platform classes

1. `SqlitePlatform` => `SQLitePlatform`
2. `SqliteSchemaManager` => `SQLiteSchemaManager`

## BC BREAK: removed `SqlitePlatform` methods.

1. `getTinyIntTypeDeclarationSQL()`,
2. `getMediumIntTypeDeclarationSQL()`.

## BC BREAK: removed `AbstractPlatform` methods.

1. `getColumnsFieldDeclarationListSQL()`,
2. `getCustomTypeDeclarationSQL()`,
3. `getDefaultSchemaName()`,
4. `getIdentitySequenceName()`,
5. `getIndexFieldDeclarationListSQL()`,
6. `supportsCreateDropDatabase()`,
7. `usesSequenceEmulatedIdentityColumns()`.

## BC BREAK: removed support for the `NULL` value of schema asset filter.

The argument of `Configuration::setSchemaAssetsFilter()` is now required and non-nullable.

## BC BREAK: removed support for custom schema options.

The following `Column` class properties and methods have been removed:

- `$_customSchemaOptions`,
- `setCustomSchemaOption()`,
- `hasCustomSchemaOption()`,
- `getCustomSchemaOption()`,
- `setCustomSchemaOptions()`,
- `getCustomSchemaOptions()`.

## BC BREAK: removed `array` and `object` column types.

The following classes and constants have been removed:
- `ArrayType`,
- `ObjectType`,
- `Types::ARRAY`,
- `Types::OBJECT`.

## BC BREAK: removed `Driver::getSchemaManager()`

The `Driver::getSchemaManager()` method has been removed.

## BC BREAK: removed `AbstractSchemaManager` methods

The `AbstractSchemaManager::getDatabasePlatform()` and `::listTableDetails()` methods have been removed.

## BC BREAK: removed Schema Visitor API.

The following interfaces and classes have been removed:

1. `Doctrine\DBAL\Schema\Visitor`,
2. `Doctrine\DBAL\Schema\NamespaceVisitor`,
3. `Doctrine\DBAL\Schema\AbstractVisitor`.

The following methods have been removed:

1. `Doctrine\DBAL\Schema\Schema::visit()`,
2. `Doctrine\DBAL\Schema\Table::visit()`,
3. `Doctrine\DBAL\Schema\Sequence::visit()`.

## BC BREAK: removed `RemoveNamespacedAssets`.

The `RemoveNamespacedAssets` schema visitor has been removed.

## BC BREAK: removed the functionality of checking schema for the usage of reserved keywords.

The following components have been removed:

1. The `dbal:reserved-words` console command.
2. The `ReservedWordsCommand` and `ReservedKeywordsValidator` classes.
3. The `KeywordList::getName()` method.

## BC BREAK: removed `AbstractPlatform::supportsForeignKeyConstraints()`.

The `AbstractPlatform::supportsForeignKeyConstraints()` method has been removed.

## BC BREAK: foreign key DDL is generated on MySQL regardless of the storage engine.

The DBAL generates DDL for foreign keys regardless of the MySQL storage engines used by the table
that owns the foreign key constraint.

## BC BREAK: removed `AbstractPlatform` methods exposing quote characters.

The `AbstractPlatform::getStringLiteralQuoteCharacter()` and `::getIdentifierQuoteCharacter()` methods
have been removed.

## Deprecated: `AbstractPlatform::CREATE_*` constants

The `AbstractPlatform::CREATE_INDEXES` and `::CREATE_FOREIGNKEYS` constants have been deprecated
as they no longer have any effect on the behavior of the `AbstractPlatform::getCreateTableSQL()` method.

## BC BREAK: removed `$createFlags` from `AbstractPlatform::getCreateTableSQL()`

The `$createFlags` parameter of `AbstractPlatform::getCreateTableSQL()` has been removed.

## BC BREAK: removed `CreateSchemaSqlCollector` and `DropSchemaSqlCollector`

The `CreateSchemaSqlCollector` and `DropSchemaSqlCollector` classes have been removed.

## BC BREAK: remove support for transaction nesting without savepoints

Starting a transaction inside another transaction with
`Doctrine\DBAL\Connection::beginTransaction()` now always results in
savepoints being used.

In case your platform does not support savepoints, you will have to
rework your application logic so as to avoid nested transaction blocks.

## Deprecated: configuration methods related to transaction nesting

Since it is no longer possible to configure whether transaction nesting is
emulated with savepoints or not, configuring that behavior has no effect and it
is deprecated to attempt to change it or to know how it is configured. As a
result, the following methods are deprecated:

- `Connection::setNestTransactionsWithSavepoints()`
- `Connection::getNestTransactionsWithSavepoints()`

## BC BREAK: Auto-increment columns on PostgreSQL are implemented as `IDENTITY`, not `SERIAL`.

Instead of using `SERIAL*` column types for `autoincrement` columns, the DBAL will now use
the `GENERATED BY DEFAULT AS IDENTITY` clause.

The upgrade to DBAL 4 will require manual migration of the database schema.
See the [documentation](docs/en/how-to/postgresql-identity-migration.rst) for more details.

## Removed the `doctrine-dbal` binary and the `ConsoleRunner` class.

The documentation explains how the console tools can be bootstrapped for standalone usage.

## Removed support for the `$database` parameter of `AbstractSchemaManager::list*()` methods

Passing `$database` to the following methods is no longer supported:

- `AbstractSchemaManager::listSequences()`,
- `AbstractSchemaManager::listTableColumns()`,
- `AbstractSchemaManager::listTableForeignKeys()`.

## Removed `AbstractPlatform` schema introspection methods

The following schema introspection methods have been removed:

- `AbstractPlatform::getListTablesSQL()`,
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
If the number is greater than `PHP_INT_MAX`, the number of affected rows may be returned as a string if the driver supports it.

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

## BC BREAK: Removed support for MariaDB 10.4.2 and older

MariaDB 10.4.2 and older are not supported anymore. The following classes have been removed:

* `Doctrine\DBAL\Platforms\MariaDb1027Platform`
* `Doctrine\DBAL\Platforms\MariaDB1043Platform`
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

The following methods are removed:

- `AbstractPlatform::hasNativeJsonType()`
- `AbstractPlatform::hasNativeGuidType()`
- `AbstractPlatform::isCommentedDoctrineType()`
- `AbstractPlatform::initializeCommentedDoctrineTypes()`
- `AbstractPlatform::markDoctrineTypeCommented()`
- `Type::requiresSQLCommentHint()`

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
2. `AbstractSchemaManager::dropAndCreateDatabase()`,
3. `AbstractSchemaManager::dropAndCreateForeignKey()`,
4. `AbstractSchemaMVersionAwarePlatformDriveranager::dropAndCreateIndex()`,
5. `AbstractSchemaManager::dropAndCreateSequence()`,
6. `AbstractSchemaManager::dropAndCreateTable()`,
7. `AbstractSchemaManager::dropAndCreateView()`,
8. `AbstractSchemaManager::tryMethod()`.

## BC BREAK: Removed support for SQL Server 2016 and older

DBAL is now tested only with SQL Server 2017 and newer.

## BC BREAK: `Statement::execute()` marked private.

The `Statement::execute()` method has been marked private.

## BC BREAK: Removed `QueryBuilder` methods and contstants.

The following `QueryBuilder` methods have been removed:

1. `execute()`,
2. `getState()`,
3. `getType()`,
4. `getConnection()`.

The following `QueryBuilder` constants have been removed:

1. `SELECT`,
2. `DELETE`,
3. `UPDATE`,
4. `INSERT`,
5. `STATE_DIRTY`,
6. `STATE_CLEAN`.

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

## BC BREAK: removed `TableDiff::$name` name `TableDiff::getName()`.

The `TableDiff::$name` property and `TableDiff::getName()` method have been removed.

## BC BREAK: removed support for renaming tables via `TableDiff` and `AbstractPlatform::alterTable()`.

The `TableDiff::$newName` property and the `TableDiff::getNewName()` method have been removed.

## BC BREAK: removed `SchemaDiff` reference to the original schema.

The `SchemaDiff` class no longer accepts or exposes a reference to the original schema.

## BC BREAK: Changes in the `ColumnDiff` class

1. The `$fromColumn` parameter of the `ColumnDiff` constructor has been made required.
2. The `$oldColumnName` property and the `getOldColumnName()` method have been removed.

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

## Removed `Comparator` methods

The `Comparator::compare()`, `::diffTable()` and `::diffColumn()` methods have been removed.

## Removed `ColumnDiff` methods

The `ColumnDiff::hasChanged()` method has been removed.

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
- `AbstractSchemaManager::createSchema()`,
- `AbstractSchemaManager::listNamespaceNames()`,
- `AbstractSchemaManager::getPortableNamespacesList()`,
- `AbstractSchemaManager::getPortableNamespaceDefinition()`,
- `PostgreSQLSchemaManager::getSchemaNames()`.

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

## BC BREAK: Changes in handling string and binary columns

- When generating schema DDL, DBAL no longer provides the default length for string and binary columns. The application may need to provide the column length if required by the target platform.
- The `\DBAL\Platforms\AbstractPlatform::getVarcharTypeDeclarationSQL()` method has been renamed to `::getStringTypeDeclarationSQL()`.
- The following `AbstractPlatform` methods have been removed as no longer relevant: `::getCharMaxLength()`, `::getVarcharMaxLength()`, `::getVarcharDefaultLength()`, `::getBinaryMaxLength()`, `::getBinaryDefaultLength()`.

## BC BREAK: Changes in `Doctrine\DBAL\Event\SchemaCreateTableEventArgs`

Table columns are no longer indexed by column name. Use the `name` attribute of the column instead.

## BC BREAK: Changes in the `Doctrine\DBAL\Schema` API

- Column precision no longer defaults to 10. The default value is NULL.
- Asset names are no longer nullable. An empty asset name should be represented as an empty string.

## BC BREAK: Changes in the `Doctrine\DBAL\Event` API

- `SchemaAlterTableAddColumnEventArgs::addSql()` and the same method in other `SchemaEventArgs`-based classes no longer accept an array of SQL statements. They accept a variadic string.

## BC BREAK: Changes in the `Doctrine\DBAL\Schema` API

- Method `Doctrine\DBAL\Schema\AbstractSchemaManager::_getPortableViewDefinition()` no longer optionally returns false. It will always return a `Doctrine\DBAL\Schema\View` instance.
- Property `Doctrine\DBAL\Schema\Table::$_primaryKeyName` is now optionally null instead of false.
- Method `Doctrine\DBAL\Schema\AbstractSchemaManager::tablesExist()` no longer accepts a string. Use `Doctrine\DBAL\Schema\AbstractSchemaManager::tableExists()` instead.
- Method `Doctrine\DBAL\Schema\OracleSchemaManager::createDatabase()` no longer accepts `null` for `$database` argument.

## BC BREAK PostgreSqlPlatform ForeignKeyConstraint support for `feferred` misspelling removed

`PostgreSqlPlatform::getAdvancedForeignKeyOptionsSQL()` had a typo in it in 2.x. Both the option name
`feferred` and `deferred` were supported in `2.x` but the misspelling was removed in 3.x.

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

Note that `OraclePlatform::getSubstringExpression()` will no longer automatically format the values of the `$start` and `$length` parameters as integers. The caller of the method is responsible for the validity of the SQL expressions.

## BC BREAK The type of `$char` in `AbstractPlatform::getTrimExpression()` changed from `string|false` to `?string`

The default value of `$char` is now `null`, not `false`. Additionally, the method will throw an `InvalidArgumentException` in an invalid value of `$mode` is passed.

## BC BREAK `Statement::quote()` only accepts strings.

`Statement::quote()` and `ExpressionBuilder::literal()` no longer accept arguments of an arbitrary type and and don't implement type-specific handling. Only strings can be quoted.

## BC BREAK `Statement` and `Connection` methods return `void`.

`Connection::connect()`, `::bindValue()` and `::execute()` no longer return a boolean value. They will throw an exception in case of failure.

## BC BREAK Transaction-related `Statement` methods return `void`.

`Statement::beginTransaction()`, `::commit()` and `::rollBack()` no longer return a boolean value. They will throw a `DriverException` in case of failure.

## MINOR BC BREAK `Statement::fetchColumn()` with an invalid index.

Similarly to `PDOStatement::fetchColumn()`, DBAL statements throw an exception in case of an invalid column index.

## BC BREAK `Statement::execute()` with redundant parameters.

Similarly to the drivers based on `pdo_pgsql` and `pdo_sqlsrv`, `OCI8Statement::execute()` and `MySQLiStatement::execute()` do not longer ignore redundant parameters.

## BC BREAK: The `NULL` value of `$offset` in LIMIT queries is not allowed

The `NULL` value of the `$offset` argument in `AbstractPlatform::(do)?ModifyLimitQuery()` methods is no longer allowed. The absence of the offset should be indicated with a `0` which is now the default value.

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

# Upgrade to 3.8

## Deprecated lock-related `AbstractPlatform` methods

The usage of `AbstractPlatform::getReadLockSQL()`, `::getWriteLockSQL()` and `::getForUpdateSQL()` is deprecated as
this API is not portable. Use `QueryBuilder::forUpdate()` as a replacement for the latter.

## Deprecated `AbstractMySQLPlatform` methods

* `AbstractMySQLPlatform::getColumnTypeSQLSnippets()` has been deprecated
  in favor of `AbstractMySQLPlatform::getColumnTypeSQLSnippet()`.
* `AbstractMySQLPlatform::getDatabaseNameSQL()` has been deprecated without replacement.
* Not passing a database name to `AbstractMySQLPlatform::getColumnTypeSQLSnippet()` has been deprecated.

## Deprecated reset methods from `QueryBuilder`

`QueryBuilder::resetQueryParts()` has been deprecated.

Resetting individual query parts through the generic `resetQueryPart()` method has been deprecated as well.
However, several replacements have been put in place depending on the `$queryPartName` parameter:

| `$queryPartName` | suggested replacement                      |
|------------------|--------------------------------------------|
| `'select'`       | Call `select()` with a new set of columns. |
| `'distinct'`     | `distinct(false)`                          |
| `'where'`        | `resetWhere()`                             |
| `'groupBy'`      | `resetGroupBy()`                           |
| `'having'`       | `resetHaving()`                            |
| `'orderBy'`      | `resetOrderBy()`                           |
| `'values'`       | Call `values()` with a new set of values.  |

## Deprecated getting query parts from `QueryBuilder`

The usage of `QueryBuilder::getQueryPart()` and `::getQueryParts()` is deprecated. The query parts
are implementation details and should not be relied upon.

# Upgrade to 3.6

## Deprecated not setting a schema manager factory

DBAL 4 will change the way the schema manager is created. To opt in to the new
behavior, please configure the schema manager factory:

```php
$configuration = new Configuration();
$configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

$connection = DriverManager::getConnection(
    [/* your parameters */],
    $configuration,
);
```

If you use a custom platform implementation, please make sure it implements
the `createSchemaManager()`method . Otherwise, the connection will fail to
create a schema manager.

## Deprecated the `url` connection parameter

DBAL ships with a new and configurable DSN parser that can be used to parse a
database URL into connection parameters understood by `DriverManager`.

### Before

```php
$connection = DriverManager::getConnection(
    ['url' => 'mysql://my-user:t0ps3cr3t@my-host/my-database']
);
```

### After

```php
$dsnParser  = new DsnParser(['mysql' => 'pdo_mysql']);
$connection = DriverManager::getConnection(
    $dsnParser->parse('mysql://my-user:t0ps3cr3t@my-host/my-database')
);
```

## Deprecated `Connection::PARAM_*_ARRAY` constants

Use the corresponding constants on `ArrayParameterType` instead. Please be aware that
`ArrayParameterType` will be a native enum type in DBAL 4.

# Upgrade to 3.5

## Deprecated extension via Doctrine Event Manager

Extension of the library behavior via Doctrine Event Manager has been deprecated.

The following methods and properties have been deprecated:
- `AbstractPlatform::$_eventManager`,
- `AbstractPlatform::getEventManager()`,
- `AbstractPlatform::setEventManager()`,
- `Connection::$_eventManager`,
- `Connection::getEventManager()`.

## Deprecated extension via connection events

Subscription to the `postConnect` event has been deprecated. Use one of the following replacements for the standard
event listeners or implement a custom middleware instead.

The following `postConnect` event listeners have been deprecated:
1. `OracleSessionInit`. Use `Doctrine\DBAL\Driver\OCI8\Middleware\InitializeSession`.
2. `SQLiteSessionInit`. Use `Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys`.
3. `SQLSessionInit`. Implement a custom middleware.

## Deprecated extension via transaction events

Subscription to the following events has been deprecated:
- `onTransactionBegin`,
- `onTransactionCommit`,
- `onTransactionRollBack`.

The upgrade path will depend on the use case:
1. If you need to extend the behavior of only the actual top-level transactions (not the ones emulated via savepoints),
   implement a driver middleware.
2. If you need to extend the behavior of the top-level and nested transactions, either implement a driver middleware
   or implement a custom wrapper connection.

## Deprecated extension via schema definition events

Subscription to the following events has been deprecated:
- `onSchemaColumnDefinition`,
- `onSchemaIndexDefinition`.

Use a custom schema manager instead.

## Deprecated extension via schema manipulation events

Subscription to the following events has been deprecated:
- `onSchemaCreateTable`,
- `onSchemaCreateTableColumn`,
- `onSchemaDropTable`,
- `onSchemaAlterTable`,
- `onSchemaAlterTableAddColumn`,
- `onSchemaAlterTableRemoveColumn`,
- `onSchemaAlterTableChangeColumn`,
- `onSchemaAlterTableRenameColumn`.

The upgrade path will depend on the use case:
1. If you are using the events to modify the behavior of the platform, you should extend the platform class
   and implement the corresponding logic in the sub-class.
2. If you are using the events to modify the arguments processed by the platform (e.g. modify the table definition
   before the platform generates the `CREATE TABLE` DDL), you should do the needed modifications before calling
   the corresponding platform or schema manager method.

## Deprecated the emulation of the `LOCATE()` function for SQLite

Relying on the availability of the `LOCATE()` on SQLite deprecated. SQLite does not provide that function natively,
but the function `INSTR()` can be a drop-in replacement in most situations. Use
`AbstractPlatform::getLocateExpression()` if you need a portable solution.

## Deprecated `SchemaDiff::toSql()` and `SchemaDiff::toSaveSql()`

Using `SchemaDiff::toSql()` to generate SQL representing the diff has been deprecated.
Use `AbstractPlatform::getAlterSchemaSQL()` instead.

`SchemaDiff::toSaveSql()` has been deprecated without a replacement.

## Deprecated `SchemaDiff::$orphanedForeignKeys`

Relying on the schema diff tracking foreign keys referencing the tables that have been dropped is deprecated.
Before dropping a table referenced by foreign keys, drop the foreign keys first.

## Deprecated the `userDefinedFunctions` driver option for `pdo_sqlite`

Instead of funneling custom functions through the `userDefinedFunctions` option, use `getNativeConnection()`
to access the wrapped PDO connection and register your custom functions directly.

### Before

```php
$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'path' => '/path/to/file.db',
    'driverOptions' => [
        'userDefinedFunctions' => [
            'my_function' => ['callback' => [SomeClass::class, 'someMethod'], 'numArgs' => 2],
        ],
    ]
]);
```

### After

```php
$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'path' => '/path/to/file.db',
]);

$connection->getNativeConnection()
    ->sqliteCreateFunction('my_function', [SomeClass::class, 'someMethod'], 2);
```

## Deprecated `Table` methods.

The `hasPrimaryKey()` method has been deprecated. Use `getPrimaryKey()` and check if the return value is not null.
The `getPrimaryKeyColumns()` method has been deprecated. Use `getPrimaryKey()` and `Index::getColumns()` instead.
The `getForeignKeyColumns()` method has been deprecated. Use `getForeignKey()`
and `ForeignKeyConstraint::getLocalColumns()` instead.
The `changeColumn()` method has been deprecated. Use `modifyColumn()` instead.

## Deprecated `SchemaException` error codes.

Relying on the error code of `SchemaException` is deprecated. In order to handle a specific type of exception,
catch the corresponding exception class instead.

| Error Code                  | Class                          |
|-----------------------------|--------------------------------|
| `TABLE_DOESNT_EXIST`        | `TableDoesNotExist`            |
| `TABLE_ALREADY_EXISTS`      | `TableAlreadyExists`           |
| `COLUMN_DOESNT_EXIST`       | `ColumnDoesNotExist`           |
| `COLUMN_ALREADY_EXISTS`     | `ColumnAlreadyExists`          |
| `INDEX_DOESNT_EXIST`        | `IndexDoesNotExist`            |
| `INDEX_ALREADY_EXISTS`      | `IndexAlreadyExists`           |
| `SEQUENCE_DOENST_EXIST`     | `SequenceDoesNotExist`         |
| `SEQUENCE_ALREADY_EXISTS`   | `SequenceAlreadyExists`        |
| `FOREIGNKEY_DOESNT_EXIST`   | `ForeignKeyDoesNotExist`       |
| `CONSTRAINT_DOESNT_EXIST`   | `UniqueConstraintDoesNotExist` |
| `NAMESPACE_ALREADY_EXISTS`  | `NamespaceAlreadyExists`       |

## Deprecated fallback connection used to determine the database platform.

Relying on a fallback connection used to determine the database platform while connecting to a non-existing database
has been deprecated. Either use an existing database name in connection parameters or omit the database name
if the platform and the server configuration allow that.

## Deprecated misspelled isFullfilledBy() method

This method's name was spelled incorrectly. Use `isFulfilledBy` instead.

## Deprecated default PostgreSQL connection database.

Relying on the DBAL connecting to the "postgres" database by default is deprecated. Unless you want to have the server
determine the default database for the connection, specify the database name explicitly.

## Deprecated the "default_dbname" parameter of the wrapper `Connection`.

The "default_dbname" parameter of the wrapper `Connection` has been deprecated. Use "dbname" instead.

## Deprecated the "platform" parameter of the wrapper `Connection`.

The "platform" parameter of the wrapper `Connection` has been deprecated. Use a driver middleware that would instantiate
the platform instead.

## Deprecated driver name aliases.

Relying on driver name aliases in connection parameters has been deprecated. Use the actual driver names instead.

## Deprecated "unique" and "check" column properties.

The "unique" and "check" column properties have been deprecated. Use unique constraints to define unique columns.

## Deprecated relying on the default precision and scale of decimal columns.

Relying on the default precision and scale of decimal columns provided by the DBAL is deprecated.
When declaring decimal columns, specify the precision and scale explicitly.

## Deprecated `Comparator::diffTable()` method.

The `Comparator::diffTable()` method has been deprecated in favor of `Comparator::compareTables()`
and `TableDiff::isEmpty()`.

Instead of having to check whether the diff is equal to the boolean `false`, you can optionally check
if the returned table diff is empty.

### Before

```php
$diff = $comparator->diffTable($oldTable, $newTable);

// mandatory check
if ($diff !== false) {
    // we have a diff
}
```

### After

```php
$diff = $comparator->compareTables($oldTable, $newTable);

// optional check
if (! $diff->isEmpty()) {
    // we have a diff
}
```

## Deprecated not passing `$fromTable` to the `TableDiff` constructor.

Not passing `$fromTable` to the `TableDiff` constructor has been deprecated.

The `TableDiff::$name` property and the `TableDiff::getName()` method have been deprecated as well. In order to obtain
the name of the table that the diff describes, use `TableDiff::getOldTable()`.

## Deprecated renaming tables via `TableDiff` and `AbstractPlatform::alterTable()`.

Renaming tables via setting the `$newName` property on a `TableDiff` and passing it to `AbstractPlatform::alterTable()`
is deprecated. The implementations of `AbstractSchemaManager::alterTable()` should use `AbstractPlatform::renameTable()`
instead.

The `TableDiff::$newName` property and the `TableDiff::getNewName()` method have been deprecated.

## Marked `Comparator` methods as internal.

The following `Comparator` methods have been marked as internal:

- `columnsEqual()`,
- `diffForeignKey()`,
- `diffIndex()`.

The `diffColumn()` method has been deprecated. Use `diffTable()` instead.

## Marked `SchemaDiff` public properties as internal.

The public properties of the `SchemaDiff` class have been marked as internal. Use the following corresponding methods
instead:

| Property             | Method                  |
|----------------------|-------------------------|
| `$newNamespaces`     | `getCreatedSchemas()`   |
| `$removedNamespaces` | `getDroppedSchemas()`   |
| `$newTables`         | `getCreatedTables()`    |
| `$changedTables`     | `getAlteredTables()`    |
| `$removedTables`     | `getDroppedTables()`    |
| `$newSequences`      | `getCreatedSequences()` |
| `$changedSequences`  | `getAlteredSequence()`  |
| `$removedSequences`  | `getDroppedSequences()` |

## Marked `TableDiff` public properties as internal.

The public properties of the `TableDiff` class have been marked as internal. Use the following corresponding methods
instead:

| Property               | Method                     |
|------------------------|----------------------------|
| `$addedColumns`        | `getAddedColumns()`        |
| `$changedColumns`      | `getModifiedColumns()`     |
| `$removedColumns`      | `getDroppedColumns()`      |
| `$renamedColumns`      | `getRenamedColumns()`      |
| `$addedIndexes`        | `getAddedIndexes()`        |
| `$changedIndexes`      | `getModifiedIndexes()`     |
| `$removedIndexes`      | `getDroppedIndexes()`      |
| `$renamedIndexes`      | `getRenamedIndexes()`      |
| `$addedForeignKeys`    | `getAddedForeignKeys()`    |
| `$changedForeignKeys`  | `getModifiedForeignKeys()` |
| `$removedForeignKeys`  | `getDroppedForeignKeys()`  |

## Marked `ColumnDiff` public properties as internal.

The `$fromColumn` and `$column` properties of the `ColumnDiff` class have been marked as internal. Use the
`getOldColumn()` and `getNewColumn()` methods instead.

## Deprecated `ColumnDiff::$changedProperties` and `::hasChanged()`.

The `ColumnDiff::$changedProperties` property and the `hasChanged()` method have been deprecated. Use one of the
following `ColumnDiff` methods in order to check if a given column property has changed:

- `hasTypeChanged()`,
- `hasLengthChanged()`,
- `hasPrecisionChanged()`,
- `hasScaleChanged()`,
- `hasUnsignedChanged()`,
- `hasFixedChanged()`,
- `hasNotNullChanged()`,
- `hasDefaultChanged()`,
- `hasAutoIncrementChanged()`,
- `hasCommentChanged()`.

## Deprecated `ColumnDiff` APIs dedicated to the old column name.

The `$oldColumnName` property and the `getOldColumnName()` method of the `ColumnDiff` class have been deprecated.

Make sure the `$fromColumn` argument is passed to the `ColumnDiff` constructor and use the `$fromColumn` property
instead.

## Marked schema diff constructors as internal.

The constructors of the following classes have been marked as internal:

1. `SchemaDiff`,
2. `TableDiff`,
3. `ColumnDiff`.

These classes can be instantiated only by schema comparators. The signatures of the constructors may change in future
versions.

## Deprecated `SchemaDiff` reference to the original schema.

The `SchemaDiff::$fromSchema` property has been deprecated.

## Marked `AbstractSchemaManager::_execSql()` as internal.

The `AbstractSchemaManager::_execSql()` method has been marked as internal. It will not be available in 4.0.

## Deprecated `AbstractSchemaManager` schema introspection methods.

The following `AbstractSchemaManager` methods has been deprecated:

1. `listTableDetails()`. Use `introspectTable()` instead,
2. `createSchema()`. Use `introspectSchema()` instead.

# Upgrade to 3.4

## Deprecated wrapper- and driver-level `Statement::bindParam()` methods.

The following methods have been deprecated:

1. `Doctrine\DBAL\Statement::bindParam()`,
2. `Doctrine\DBAL\Driver\Statement::bindParam()`.

Use the corresponding `bindValue()` instead.

## Deprecated not passing parameter type to the driver-level `Statement::bind*()` methods.

Not passing `$type` to the driver-level `Statement::bindParam()` and `::bindValue()` is deprecated.
Pass the type corresponding to the parameter being bound.

## Deprecated passing `$params` to `Statement::execute*()` methods.

Passing `$params` to the driver-level `Statement::execute()` and the wrapper-level `Statement::executeQuery()`
and `Statement::executeStatement()` methods has been deprecated.

Bind parameters using `Statement::bindParam()` or `Statement::bindValue()` instead.

## Deprecated `QueryBuilder` methods and constants.

1. The `QueryBuilder::getState()` method has been deprecated as the builder state is an internal concern.
2. Relying on the type of the query being built by using `QueryBuilder::getType()` has been deprecated.
   If necessary, track the type of the query being built outside of the builder.
3. The `QueryBuilder::getConnection()` method has been deprecated. Use the connection used to instantiate the builder
   instead.

The following `QueryBuilder` constants related to the above methods have been deprecated:

1. `SELECT`,
2. `DELETE`,
3. `UPDATE`,
4. `INSERT`,
5. `STATE_DIRTY`,
6. `STATE_CLEAN`.

## Marked `Connection::ARRAY_PARAM_OFFSET` as internal.

The `Connection::ARRAY_PARAM_OFFSET` constant has been marked as internal. It will be removed in 4.0.

## Deprecated using NULL as prepared statement parameter type.

Omit the type or use `ParameterType::STRING` instead.

## Deprecated passing asset names as assets in `AbstractPlatform` and `AbstractSchemaManager` methods.

Passing assets to the following `AbstractPlatform` methods and parameters has been deprecated:

1. The `$table` parameter of `getDropTableSQL()`,
2. The `$table` parameter of `getDropTemporaryTableSQL()`,
3. The `$index` and `$table` parameters of `getDropIndexSQL()`,
4. The `$constraint` and `$table` parameters of `getDropConstraintSQL()`,
5. The `$foreignKey` and `$table` parameters of `getDropForeignKeySQL()`,
6. The `$sequence` parameter of `getDropSequenceSQL()`,
7. The `$table` parameter of `getCreateConstraintSQL()`,
8. The `$table` parameter of `getCreatePrimaryKeySQL()`,
9. The `$table` parameter of `getCreateForeignKeySQL()`.

Passing assets to the following `AbstractSchemaManager` methods and parameters has been deprecated:

1. The `$index` and `$table` parameters of `dropIndex()`,
2. The `$table` parameter of `dropConstraint()`,
3. The `$foreignKey` and `$table` parameters of `dropForeignKey()`.

Pass a string representing the quoted asset name instead.

## Marked `AbstractPlatform` methods as internal.

The following methods have been marked internal as they are not designed to be used from outside the platform classes:

1. `getAdvancedForeignKeyOptionsSQL()`,
2. `getColumnCharsetDeclarationSQL()`,
3. `getColumnCollationDeclarationSQL()`,
4. `getColumnDeclarationSQL()`,
5. `getCommentOnColumnSQL()`,
6. `getDefaultValueDeclarationSQL()`,
7. `getForeignKeyDeclarationSQL()`,
8. `getForeignKeyReferentialActionSQL()`,
9. `getIndexDeclarationSQL()`,
10. `getInlineColumnCommentSQL()`,
11. `supportsColumnCollation()`,
12. `supportsCommentOnStatement()`,
13. `supportsInlineColumnComments()`,
14. `supportsPartialIndexes()`.

## Deprecated internal `AbstractPlatform` methods.

The following methods have been deprecated as they do not represent any platform-level abstraction:

1. `getCustomTypeDeclarationSQL()`,
2. `getIndexFieldDeclarationListSQL()`,
3. `getColumnsFieldDeclarationListSQL()`.

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

## `AbstractPlatform` schema introspection methods made internal

The following schema introspection methods have been marked as internal:

- `AbstractPlatform::getListDatabasesSQL()`,
- `AbstractPlatform::getListSequencesSQL()`,
- `AbstractPlatform::getListViewsSQL()`.

The queries used for schema introspection are an internal implementation detail of the DBAL.

## Deprecated `collate` option for MySQL

This undocumented option is deprecated in favor of `collation`.

## Deprecated `AbstractPlatform::getListTableConstraintsSQL()`

This method is unused by the DBAL since 2.0.

## Deprecated `Type::getName()`

This method is not useful for the DBAL anymore, and will be removed in 4.0.
As a consequence, depending on the name of a type being `json` for `jsonb` to
be used for the Postgres platform is deprecated in favor of extending
`Doctrine\DBAL\Types\JsonType`.

You can use `Type::getTypeRegistry()->lookupName($type)` instead.

## Deprecated `AbstractPlatform::getColumnComment()`, `AbstractPlatform::getDoctrineTypeComment()`,
`AbstractPlatform::hasNative*Type()` and `Type::requiresSQLCommentHint()`

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
3. The `Statement::fetchAll()` method is replaced with `fetchAllNumeric()`, `fetchAllAssociative()` and `fetchColumn()`.
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
Please use `Doctrine\DBAL\Platforms\SQLServerPlatform` instead.

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
