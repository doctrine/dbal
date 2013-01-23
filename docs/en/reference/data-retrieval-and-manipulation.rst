Data Retrieval And Manipulation
===============================

Doctrine DBAL follows the PDO API very closely. If you have worked with PDO
before you will get to know Doctrine DBAL very quickly. On top of the API provided
by PDO there are tons of convenience functions in Doctrine DBAL.

Data Retrieval
--------------

Using a database implies retrieval of data. It is the primary use-case of a database.
For this purpose each database vendor exposes a Client API that can be integrated into
programming languages. PHP has a generic abstraction layer for this
kind of API called PDO (PHP Data Objects). However because of disagreements
between the PHP community there are often native extensions for each database
vendor that are much more maintained (OCI8 for example).

Doctrine DBAL API builds on top of PDO and integrates native extensions by
wrapping them into the PDO API as well. If you already have an open connection
through the ``Doctrine\DBAL\DriverManager::getConnection()`` method you
can start using this API for data retrieval easily.

Start writing an SQL query and pass it to the ``query()`` method of your
connection:

.. code-block:: php

    <?php
    use Doctrine\DBAL\DriverManager;

    $conn = DriverManager::getConnection($params, $config);

    $sql = "SELECT * FROM articles";
    $stmt = $conn->query($sql); // Simple, but has several drawbacks

The query method executes the SQL and returns a database statement object.
A database statement object can be iterated to retrieve all the rows that matched
the query until there are no more rows:

.. code-block:: php

    <?php

    while ($row = $stmt->fetch()) {
        echo $row['headline'];
    }

The query method is the most simple one for fetching data, but it also has
several drawbacks:

-   There is no way to add dynamic parameters to the SQL query without modifying
    ``$sql`` itself. This can easily lead to a category of security
    holes called **SQL injection**, where a third party can modify the SQL executed 
    and even execute their own queries through clever exploiting of the security hole.
-   **Quoting** dynamic parameters for an SQL query is tedious work and requires lots
    of use of the ``Doctrine\DBAL\Connection#quote()`` method, which makes the
    original SQL query hard to read/understand.
-   Databases optimize SQL queries before they are executed. Using the query method
    you will trigger the optimization process over and over again, although
    it could re-use this information easily using a technique called **prepared statements**.

This three arguments and some more technical details hopefully convinced you to investigate
prepared statements for accessing your database. 

Dynamic Parameters and Prepared Statements
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Consider the previous query, now parameterized to fetch only a single article by id.
Using **ext/mysql** (still the primary choice of MySQL access for many developers) you had to escape
every value passed into the query using ``mysql_real_escape_string()`` to avoid SQL injection:

.. code-block:: php

    <?php
    $sql = "SELECT * FROM articles WHERE id = '" . mysql_real_escape_string($id, $link) . "'";
    $rs = mysql_query($sql);

If you start adding more and more parameters to a query (for example in UPDATE or INSERT statements)
this approach might lead to complex to maintain SQL queries. The reason is simple, the actual
SQL query is not clearly separated from the input parameters. Prepared statements separate
these two concepts by requiring the developer to add **placeholders** to the SQL query (prepare) which
are then replaced by their actual values in a second step (execute).

.. code-block:: php

    <?php
    // $conn instanceof Doctrine\DBAL\Connection

    $sql = "SELECT * FROM articles WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, $id);
    $stmt->execute();

Placeholders in prepared statements are either simple positional question marks (?) or named labels starting with
a double-colon (:name1). You cannot mix the positional and the named approach. The approach
using question marks is called positional, because the values are bound in order from left to right
to any question mark found in the previously prepared SQL query. That is why you specify the
position of the variable to bind into the ``bindValue()`` method:

.. code-block:: php

    <?php
    // $conn instanceof Doctrine\DBAL\Connection

    $sql = "SELECT * FROM articles WHERE id = ? AND status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, $id);
    $stmt->bindValue(2, $status);
    $stmt->execute();

Named parameters have the advantage that their labels can be re-used and only need to be bound once:

.. code-block:: php

    <?php
    // $conn instanceof Doctrine\DBAL\Connection

    $sql = "SELECT * FROM users WHERE name = :name OR username = :name";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue("name", $name);
    $stmt->execute();

The following section describes the API of Doctrine DBAL with regard to prepared statements.

.. note::

    Support for positional and named prepared statements varies between the different
    database extensions. PDO implements its own client side parser so that both approaches
    are feasible for all PDO drivers. OCI8/Oracle only supports named parameters, but
    Doctrine implements a client side parser to allow positional parameters also.

Using Prepared Statements
~~~~~~~~~~~~~~~~~~~~~~~~~

There are three low-level methods on ``Doctrine\DBAL\Connection`` that allow you to
use prepared statements:

-   ``prepare($sql)`` - Create a prepared statement of the type ``Doctrine\DBAL\Statement``.
    Using this method is preferred if you want to re-use the statement to execute several
    queries with the same SQL statement only with different parameters.
-   ``executeQuery($sql, $params, $types)`` - Create a prepared statement for the passed
    SQL query, bind the given params with their binding types and execute the query.
    This method returns the executed prepared statement for iteration and is useful
    for SELECT statements.
-   ``executeUpdate($sql, $params, $types)`` - Create a prepared statement for the passed
    SQL query, bind the given params with their binding types and execute the query.
    This method returns the number of affected rows by the executed query and is useful
    for UPDATE, DELETE and INSERT statements.

A simple usage of prepare was shown in the previous section, however it is useful to
dig into the features of a ``Doctrine\DBAL\Statement`` a little bit more. There are essentially
two different types of methods available on a statement. Methods for binding parameters and types
and methods to retrieve data from a statement.

-   ``bindValue($pos, $value, $type)`` - Bind a given value to the positional or named parameter
    in the prepared statement.
-   ``bindParam($pos, &$param, $type)`` - Bind a given reference to the positional or
    named parameter in the prepared statement.

If you are finished with binding parameters you have to call ``execute()`` on the statement, which
will trigger a query to the database. After the query is finished you can access the results
of this query using the fetch API of a statement:

-   ``fetch($fetchStyle)`` - Retrieves the next row from the statement or false if there are none.
    Moves the pointer forward one row, so that consecutive calls will always return the next row.
-   ``fetchColumn($column)`` - Retrieves only one column of the next row specified by column index.
    Moves the pointer forward one row, so that consecutive calls will always return the next row.
-   ``fetchAll($fetchStyle)`` - Retrieves all rows from the statement.

The fetch API of a prepared statement obviously works only for ``SELECT`` queries.

If you find it tedious to write all the prepared statement code you can alternatively use
the ``Doctrine\DBAL\Connection#executeQuery()`` and ``Doctrine\DBAL\Connection#executeUpdate()``
methods. See the API section below on details how to use them.

Additionally there are lots of convenience methods for data-retrieval and manipulation
on the Connection, which are all described in the API section below.

Binding Types
-------------

Doctrine DBAL extends PDOs handling of binding types in prepared statement
considerably. Besides the well known ``\PDO::PARAM_*`` constants you
can make use of two very powerful additional features.

Doctrine\DBAL\Types Conversion
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If you don't specify an integer (through a ``PDO::PARAM*`` constant) to
any of the parameter binding methods but a string, Doctrine DBAL will
ask the type abstraction layer to convert the passed value from
its PHP to a database representation. This way you can pass ``\DateTime``
instances to a prepared statement and have Doctrine convert them 
to the appropriate vendors database format:

.. code-block:: php

    <?php
    $date = new \DateTime("2011-03-05 14:00:21");
    $stmt = $conn->prepare("SELECT * FROM articles WHERE publish_date > ?");
    $stmt->bindValue(1, $date, "datetime");
    $stmt->execute();

If you take a look at ``Doctrine\DBAL\Types\DateTimeType`` you will see that
parts of the conversion is delegated to a method on the current database platform,
which means this code works independent of the database you are using.

.. note::

    Be aware this type conversion only works with ``Statement#bindValue()``,
    ``Connection#executeQuery()`` and ``Connection#executeUpdate()``. It
    is not supported to pass a doctrine type name to ``Statement#bindParam()``,
    because this would not work with binding by reference.

List of Parameters Conversion
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. note::

    This is a Doctrine 2.1 feature.

One rather annoying bit of missing functionality in SQL is the support for lists of parameters.
You cannot bind an array of values into a single prepared statement parameter. Consider
the following very common SQL statement:

.. code-block:: sql

    SELECT * FROM articles WHERE id IN (?)

Since you are using an ``IN`` expression you would really like to use it in the following way
(and I guess everybody has tried to do this once in his life, before realizing it doesn't work):

.. code-block:: php

    <?php
    $stmt = $conn->prepare('SELECT * FROM articles WHERE id IN (?)');
    // THIS WILL NOT WORK:
    $stmt->bindValue(1, array(1, 2, 3, 4, 5, 6));
    $stmt->execute();

Implementing a generic way to handle this kind of query is tedious work. This is why most
developers fallback to inserting the parameters directly into the query, which can open
SQL injection possibilities if not handled carefully.

Doctrine DBAL implements a very powerful parsing process that will make this kind of prepared
statement possible natively in the binding type system.
The parsing necessarily comes with a performance overhead, but only if you really use a list of parameters.
There are two special binding types that describe a list of integers or strings:

-   ``\Doctrine\DBAL\Connection::PARAM_INT_ARRAY``
-   ``\Doctrine\DBAL\Connection::PARAM_STR_ARRAY``

Using one of this constants as a type you can activate the SQLParser inside Doctrine that rewrites
the SQL and flattens the specified values into the set of parameters. Consider our previous example:

.. code-block:: php

    <?php
    $stmt = $conn->executeQuery('SELECT * FROM articles WHERE id IN (?)',
        array(array(1, 2, 3, 4, 5, 6)),
        array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
    );

The SQL statement passed to ``Connection#executeQuery`` is not the one actually passed to the
database. It is internally rewritten to look like the following explicit code that could
be specified as well:

.. code-block:: php

    <?php
    // Same SQL WITHOUT usage of Doctrine\DBAL\Connection::PARAM_INT_ARRAY
    $stmt = $conn->executeQuery('SELECT * FROM articles WHERE id IN (?, ?, ?, ?, ?, ?)',
        array(1, 2, 3, 4, 5, 6),
        array(\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT)
    );

This is much more complicated and is ugly to write generically.

.. note::

    The parameter list support only works with ``Doctrine\DBAL\Connection::executeQuery()``
    and ``Doctrine\DBAL\Connection::executeUpdate()``, NOT with the binding methods of
    a prepared statement.

API
---

The DBAL contains several methods for executing queries against
your configured database for data retrieval and manipulation. Below
we'll introduce these methods and provide some examples for each of
them.

prepare()
~~~~~~~~~

Prepare a given SQL statement and return the
``\Doctrine\DBAL\Driver\Statement`` instance:

.. code-block:: php

    <?php
    $statement = $conn->prepare('SELECT * FROM user');
    $statement->execute();
    $users = $statement->fetchAll();
    
    /*
    array(
      0 => array(
        'username' => 'jwage',
        'password' => 'changeme
      )
    )
    */

executeUpdate()
~~~~~~~~~~~~~~~

Executes a prepared statement with the given SQL and parameters and
returns the affected rows count:

.. code-block:: php

    <?php
    $count = $conn->executeUpdate('UPDATE user SET username = ? WHERE id = ?', array('jwage', 1));
    echo $count; // 1

The ``$types`` variable contains the PDO or Doctrine Type constants
to perform necessary type conversions between actual input
parameters and expected database values. See the
`Types <./types#type-conversion>`_ section for more information.

executeQuery()
~~~~~~~~~~~~~~

Creates a prepared statement for the given SQL and passes the
parameters to the execute method, then returning the statement:

.. code-block:: php

    <?php
    $statement = $conn->executeQuery('SELECT * FROM user WHERE username = ?', array('jwage'));
    $user = $statement->fetch();
    
    /*
    array(
      0 => 'jwage',
      1 => 'changeme
    )
    */

The ``$types`` variable contains the PDO or Doctrine Type constants
to perform necessary type conversions between actual input
parameters and expected database values. See the
`Types <./types#type-conversion>`_ section for more information.

fetchAll()
~~~~~~~~~~

Execute the query and fetch all results into an array:

.. code-block:: php

    <?php
    $users = $conn->fetchAll('SELECT * FROM user');
    
    /*
    array(
      0 => array(
        'username' => 'jwage',
        'password' => 'changeme
      )
    )
    */

fetchArray()
~~~~~~~~~~~~

Numeric index retrieval of first result row of the given query:

.. code-block:: php

    <?php
    $user = $conn->fetchArray('SELECT * FROM user WHERE username = ?', array('jwage'));
    
    /*
    array(
      0 => 'jwage',
      1 => 'changeme
    )
    */

fetchColumn()
~~~~~~~~~~~~~

Retrieve only the given column of the first result row.

.. code-block:: php

    <?php
    $username = $conn->fetchColumn('SELECT username FROM user WHERE id = ?', array(1), 0);
    echo $username; // jwage

fetchAssoc()
~~~~~~~~~~~~

Retrieve assoc row of the first result row.

.. code-block:: php

    <?php
    $user = $conn->fetchAssoc('SELECT * FROM user WHERE username = ?', array('jwage'));
    /*
    array(
      'username' => 'jwage',
      'password' => 'changeme
    )
    */

There are also convenience methods for data manipulation queries:

delete()
~~~~~~~~~

Delete all rows of a table matching the given identifier, where
keys are column names.

.. code-block:: php

    <?php
    $conn->delete('user', array('id' => 1));
    // DELETE FROM user WHERE id = ? (1)

insert()
~~~~~~~~~

Insert a row into the given table name using the key value pairs of
data.

.. code-block:: php

    <?php
    $conn->insert('user', array('username' => 'jwage'));
    // INSERT INTO user (username) VALUES (?) (jwage)

update()
~~~~~~~~~

Update all rows for the matching key value identifiers with the
given data.

.. code-block:: php

    <?php
    $conn->update('user', array('username' => 'jwage'), array('id' => 1));
    // UPDATE user (username) VALUES (?) WHERE id = ? (jwage, 1)

By default the Doctrine DBAL does no escaping. Escaping is a very
tricky business to do automatically, therefore there is none by
default. The ORM internally escapes all your values, because it has
lots of metadata available about the current context. When you use
the Doctrine DBAL as standalone, you have to take care of this
yourself. The following methods help you with it:

quote()
~~~~~~~~~

Quote a value:

.. code-block:: php

    <?php
    $quoted = $conn->quote('value');
    $quoted = $conn->quote('1234', \PDO::PARAM_INT);

quoteIdentifier()
~~~~~~~~~~~~~~~~~

Quote an identifier according to the platform details.

.. code-block:: php

    <?php
    $quoted = $conn->quoteIdentifier('id');

