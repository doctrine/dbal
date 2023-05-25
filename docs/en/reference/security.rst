Security
========

Allowing users of your website to communicate with a database can possibly have
security implications that you should be aware of. Databases allow very
powerful commands that not every user of your website should be able to
execute. Additionally the data in your database probably contains information
that should not be visible to everyone with access to the website.

The most dangerous security problem with regard to databases is the possibility
of SQL injections.  An SQL injection security hole allows an attacker to
execute new or modify existing SQL statements to access information that he is
not allowed to access.

Neither Doctrine DBAL nor ORM can prevent such attacks if you are careless as a
developer. This section explains to you the problems of SQL injection and how
to prevent them.

SQL Injection: Safe and Unsafe APIs for User Input
--------------------------------------------------

A database library naturally touches the class of SQL injection security
vulnerabilities. You should read the following information carefully to
understand how Doctrine can and cannot help you to prevent SQL injection.

In general you should assume that APIs in Doctrine are not safe for user input.
There are however some exceptions.

The following APIs are designed to be **SAFE** from SQL injections:

- For ``Doctrine\DBAL\Connection#insert($table, $values, $types)``,
  ``Doctrine\DBAL\Connection#update($table, $values, $where, $types)`` and
  ``Doctrine\DBAL\Connection#delete($table, $where, $types)`` only the array
  values of ``$values`` and ``$where``. The table name and keys of ``$values``
  and ``$where`` are NOT escaped.
- ``Doctrine\DBAL\Query\QueryBuilder#setFirstResult($offset)``
- ``Doctrine\DBAL\Query\QueryBuilder#setMaxResults($limit)``
- ``Doctrine\DBAL\Platforms\AbstractPlatform#modifyLimitQuery($sql, $limit, $offset)`` for the ``$limit`` and ``$offset`` parameters.

Consider **ALL** other APIs to be not safe for user-input:

- Query methods on the Connection
- The QueryBuilder API
- The Platforms and SchemaManager APIs to generate and execute DML/DDL SQL statements

To use values from the user input in those scenarios use prepared statements.

User input in your queries
--------------------------

A database application necessarily requires user-input to be passed to your queries.
There are wrong and right ways to do this and it is very important to be very strict about this:

Wrong: String Concatenation
~~~~~~~~~~~~~~~~~~~~~~~~~~~

You should never ever build your queries dynamically and concatenate user-input into your
SQL or DQL query. For Example:

.. code-block:: php

    <?php
    // Very wrong!
    $sql = "SELECT * FROM users WHERE name = '" . $_GET['username']. "'";

An attacker could inject any value into the GET variable "username" to modify the query to their needs.

Although DQL is a wrapper around SQL that can prevent some security implications, the previous
example is also a threat to DQL queries.

.. code-block:: php

    <?php
    // DQL is not safe against arbitrary user-input as well:
    $dql = "SELECT u FROM User u WHERE u.username = '" . $_GET['username'] . "'";

In this scenario an attacker could still pass a username set to ``' OR 1 = 1`` and create a valid DQL query.
Although DQL will make use of quoting functions when literals are used in a DQL statement, allowing
the attacker to modify the DQL statement with valid literals cannot be detected by the DQL parser, it
is your responsibility.

Right: Prepared Statements
~~~~~~~~~~~~~~~~~~~~~~~~~~

You should always use prepared statements to execute your queries. Prepared statements is a two-step
procedure, separating the SQL query from the parameters. They are supported (and encouraged) for both
DBAL SQL queries and for ORM DQL queries.

Instead of using string concatenation to insert user-input into your SQL/DQL statements you just specify
placeholders and then explain to the database driver which variable should be bound to
which placeholder. Each database vendor supports different placeholder styles:

-  All PDO Drivers support positional (using question marks) and named placeholders (e.g. ``:param1``, ``:foo``).
-  OCI8 only supports named parameters, but Doctrine DBAL has a thin layer around OCI8 and
   also allows positional placeholders.
-  Doctrine ORM DQL allows both named and positional parameters. The positional parameters however are not
   just question marks, but suffixed with a number (?1, ?2, ?3, ...).

Following are examples of using prepared statements with SQL and DQL:

.. code-block:: php

    <?php
    // SQL Prepared Statements: Positional
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bindValue(1, $_GET['username']);
    $resultSet = $stmt->executeQuery();

    // SQL Prepared Statements: Named
    $sql = "SELECT * FROM users WHERE username = :user";
    $stmt = $connection->prepare($sql);
    $stmt->bindValue("user", $_GET['username']);
    $resultSet = $stmt->executeQuery();

    // DQL Prepared Statements: Positional
    $dql = "SELECT u FROM User u WHERE u.username = ?1";
    $query = $em->createQuery($dql);
    $query->setParameter(1, $_GET['username']);
    $data = $query->getResult();

    // DQL Prepared Statements: Named
    $dql = "SELECT u FROM User u WHERE u.username = :name";
    $query = $em->createQuery($dql);
    $query->setParameter("name", $_GET['username']);
    $data = $query->getResult();

You can see this is a bit more tedious to write, but this is the only way to write secure queries. If you
are using just the DBAL there are also helper methods which simplify the usage quite a lot:

.. code-block:: php

    <?php
    // bind parameters and execute query at once.
    $sql = "SELECT * FROM users WHERE username = ?";
    $resultSet = $connection->executeQuery($sql, [$_GET['username']]);

There is also ``executeStatement`` which does not return a statement but the number of affected rows.

Besides binding parameters you can also pass the type of the variable. This allows Doctrine or the underlying
vendor to not only escape but also cast the value to the correct type. See the docs on querying and DQL in the
respective chapters for more information.

Discouraged: Quoting/Escaping values
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Previously we said string concatenation is wrong. There is a way to do it technically correctly using
the ``Connection#quote`` method:

.. code-block:: php

    <?php
    // Parameter quoting
    $sql = "SELECT * FROM users WHERE name = " . $connection->quote($_GET['username']);

This method is only available for SQL, not for DQL. For DQL you are always encouraged to use prepared
statements not only for security, but also for caching reasons. To insert a string literal into DDL,
use ``AbstractPlatform::quoteStringLiteral()``.
