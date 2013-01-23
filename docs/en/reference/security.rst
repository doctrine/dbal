Security
========

Allowing users of your website to communicate with a database can possibly have security implications
that you should be aware of. Databases allow very powerful commands that not every user of your website
should be able to execute. Additionally the data in your database probably contains information that
should not be visible to everyone with access to the website.

The most dangerous security problem with regard to databases is the possibility of SQL injections.
An SQL injection security hole allows an attacker to execute new or modify existing SQL statements to
access information that he is not allowed to access.

Neither Doctrine DBAL nor ORM can prevent such attacks if you are careless as a developer. This section
explains to you the problems of SQL injection and how to prevent them.

User input in your queries
--------------------------

A database application necessarily requires user-input to passed to your queries.
There are wrong and right ways to do this and is very important to be very strict about this:

Wrong: String Concatenation
~~~~~~~~~~~~~~~~~~~~~~~~~~~

You should never ever build your queries dynamically and concatenate user-input into your
SQL or DQL query. For Example:

.. code-block:: php

    <?php
    // Very wrong!
    $sql = "SELECT * FROM users WHERE name = '" . $_GET['username']. "'";

An attacker could inject any value into the GET variable "username" to modify the query to his needs.

Although DQL is a wrapper around SQL that can prevent you from some security implications, the previous
example is also a thread to DQL queries.

    <?php
    // DQL is not safe against arbitrary user-input as well:
    $dql = "SELECT u FROM User u WHERE u.username = '" . $_GET['username'] . "'";

In this scenario an attacker could still pass a username set to "' OR 1 = 1" and create a valid DQL query.
Although DQL will make use of quoting functions when literals are used in a DQL statement, allowing
the attacker to modify the DQL statement with valid literals cannot be detected by the DQL parser, it
is your responsibility.

Right: Prepared Statements
~~~~~~~~~~~~~~~~~~~~~~~~~~

You should always use prepared statements to execute your queries. Prepared statements is a two-step
procedure, separating SQL query from the parameters. They are supported (and encouraged) for both
DBAL SQL queries and for ORM DQL queries.

Instead of using string concatenation to insert user-input into your SQL/DQL statements you just specify
either placeholders instead and then explain to the database driver which variable should be bound to
which placeholder. Each database vendor supports different placeholder styles:

-  All PDO Drivers support positional (using question marks) and named placeholders (:param1, :foo, :bar).
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
    $stmt->execute();

    // SQL Prepared Statements: Named
    $sql = "SELECT * FROM users WHERE username = :user";
    $stmt = $connection->prepare($sql);
    $stmt->bindValue("user", $_GET['username']);
    $stmt->execute();

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
are using just the DBAL there are also helper methods which simplify the usage quite alot:

.. code-block:: php

    <?php
    // bind parameters and execute query at once.
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $connection->executeQuery($sql, array($_GET['username']));

There is also ``executeUpdate`` which does not return a statement but the number of affected rows.

Besides binding parameters you can also pass the type of the variable. This allows Doctrine or the underyling
vendor to not only escape but also cast the value to the correct type. See the docs on querying and DQL in the
respective chapters for more information.

Right: Quoting/Escaping values
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Although previously we said string concatenation is wrong, there is a way to do it correctly using
the ``Connection#quote`` method:

.. code-block:: php

    <?php
    // Parameter quoting
    $sql = "SELECT * FROM users WHERE name = " . $connection->quote($_GET['username'], \PDO::PARAM_STR);

This method is only available for SQL, not for DQL. For DQL it is always encouraged to use prepared
statements not only for security, but also for caching reasons.

Non-ASCII compatible Charsets in MySQL
--------------------------------------

Up until PHP 5.3.6 PDO has a security problem when using non ascii compatible charsets. Even if specifying
the charset using "SET NAMES", emulated prepared statements and ``PDO#quote`` could not reliably escape
values, opening up to potential SQL injections. If you are running PHP 5.3.6 you can solve this issue
by passing the driver option "charset" to Doctrine PDO MySQL driver. Using SET NAMES does not suffice!

.. code-block::

    <?php    
    $conn = DriverManager::getConnection(array(
        'driver' => 'pdo_mysql',
        'charset' => 'UTF8',
    ));
