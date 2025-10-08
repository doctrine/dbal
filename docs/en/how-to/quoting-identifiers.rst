.. _quoting-identifiers:

Choosing Between Unquoted and Quoted Identifiers
================================================

Database platforms supported by Doctrine DBAL differ in how they handle the case of identifiers. For background
information, see :ref:`Unquoted and Quoted Identifiers <unquoted-and-quoted-identifiers>`.

The choice between unquoted and quoted identifiers depends on the database platforms targeted by the application and the
desired behavior regarding identifier case handling.

When to Use Unquoted Identifiers
--------------------------------

Unquoted identifiers are recommended as the default option, particularly in the following cases:

1. The application targets MySQL, SQLite, or SQL Server.
2. The application targets PostgreSQL, and all identifiers are written in lowercase (for example, ``user_id``).

In these scenarios, unquoted identifiers provide consistent behavior across platforms: the object names in the database
appear as specified in the code, without any practical disadvantages.

Unquoted identifiers can also be used when targeting any database platforms, and the exact case of the identifiers in
the database is not important.

The main advantage of unquoted identifiers on SQL-92–compliant platforms (PostgreSQL, Oracle, Db2) is simplified SQL
syntax, as quoting is not required except where mandated by the SQL grammar. The drawback is that identifier case may
differ between databases (for example, being converted to upper or lower case), which can affect the keys of associative
result sets returned by database drivers.

When to Use Quoted Identifiers
------------------------------

Quoted identifiers are recommended when case preservation is required on SQL-92–compliant database platforms. Examples
include:

1. Targeting Oracle or Db2 and using identifiers with lower-case characters such as ``user_name``.
2. Targeting PostgreSQL and identifiers with upper-case characters such as ``UserName``.

The advantage of quoted identifiers is that the original case of the identifiers is preserved in the target database.
The disadvantage is that such identifiers must always be quoted when referenced in SQL statements.
