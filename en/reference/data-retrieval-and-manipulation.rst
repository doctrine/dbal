Data Retrieval And Manipulation
===============================

Doctrine DBAL follows the PDO API very closely. If you have worked with PDO
before you will get to know Doctrine DBAL very quickly. On top of API provided
by PDO there are tons of convenience functions in Doctrine DBAL.

Types
-----

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
to the apropriate vendors database format:

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

*   \Doctrine\DBAL\Connection::PARAM_INT_ARRAY
*   \Doctrine\DBAL\Connection::PARAM_STR_ARRAY

Using one of this constants as a type you can activate the SQLParser inside Doctrine that rewrites
the SQL and flattens the specified values into the set of parameters. Consider our previous example:

.. code-block:: php

    <?php
    $stmt = $conn->executeQuery('SELECT * FROM articles WHERE id IN (?)',
        array(1 => array(1, 2, 3, 4, 5, 6)),
        array(1 => \Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
    );

The sql statement passed to ``Connection#executeQuery`` is not the one actually passed to the
database. It is internally rewritten to look like the following explicit code that could
be specified aswell:

    <?php
    // Same SQL WITHOUT usage of Doctrine\DBAL\Connection::PARAM_INT_ARRAY
    $stmt = $conn->executeQuery('SELECT * FROM articles WHERE id IN (?, ?, ?, ?, ?, ?)',
        array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6),
        array(
            1 => \PDO::PARAM_INT, 2 => \PDO::PARAM_INT, 3 => \PDO::PARAM_INT,
            4 => \PDO::PARAM_INT, 5 => \PDO::PARAM_INT, 6 => \PDO::PARAM_INT,
        )
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

Prepare a given sql statement and return the
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

Executes a prepared statement with the given sql and parameters and
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

Creates a prepared statement for the given sql and passes the
parameters to the execute method, then returning the statement:

.. code-block:: php

    <?php
    $statement = $conn->execute('SELECT * FROM user WHERE username = ?', array('jwage'));
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

