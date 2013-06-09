Known Vendor Issues
===================

This section describes known compatability issues with all the
supported database vendors:

PostgreSQL
----------

DateTime, DateTimeTz and Time Types
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Postgres has a variable return format for the datatype TIMESTAMP(n)
and TIME(n) if microseconds are allowed (n > 0). Whenever you save
a value with microseconds = 0. PostgreSQL will return this value in
the format:

::

    2010-10-10 10:10:10 (Y-m-d H:i:s)

However if you save a value with microseconds it will return the
full representation:

::

    2010-10-10 10:10:10.123456 (Y-m-d H:i:s.u)

Using the DateTime, DateTimeTz or Time type with microseconds
enabled columns can lead to errors because internally types expect
the exact format 'Y-m-d H:i:s' in combination with
``DateTime::createFromFormat()``. This method is twice a fast as
passing the date to the constructor of ``DateTime``.

This is why Doctrine always wants to create the time related types
without microseconds:


-  DateTime to ``TIMESTAMP(0) WITHOUT TIME ZONE``
-  DateTimeTz to ``TIMESTAMP(0) WITH TIME ZONE``
-  Time to ``TIME(0) WITHOUT TIME ZONE``

If you do not let Doctrine create the date column types and rather
use types with microseconds you have replace the "DateTime",
"DateTimeTz" and "Time" types with a more liberal DateTime parser
that detects the format automatically:

::

    use Doctrine\DBAL\Types\Type;
    
    Type::overrideType('datetime', 'Doctrine\DBAL\Types\VarDateTimeType');
    Type::overrideType('datetimetz', 'Doctrine\DBAL\Types\VarDateTimeType');
    Type::overrideType('time', 'Doctrine\DBAL\Types\VarDateTimeType');

Timezones and DateTimeTz
~~~~~~~~~~~~~~~~~~~~~~~~

Postgres does not save the actual Timezone Name but UTC-Offsets.
The difference is subtle but can be potentially very nasty. Derick
Rethans explains it very well
`in a blog post of his <http://derickrethans.nl/storing-date-time-in-database.html>`_.

MySQL
-----

DateTimeTz
~~~~~~~~~~

MySQL does not support saving timezones or offsets. The DateTimeTz
type therefore behave like the DateTime type.

Sqlite
------

DateTimeTz
~~~~~~~~~~

Sqlite does not support saving timezones or offsets. The DateTimeTz
type therefore behave like the DateTime type.

IBM DB2
-------

DateTimeTz
~~~~~~~~~~

DB2 does not save the actual Timezone Name but UTC-Offsets. The
difference is subtle but can be potentially very nasty. Derick
Rethans explains it very well
`in a blog post of his <http://derickrethans.nl/storing-date-time-in-database.html>`_.

Oracle
------

DateTimeTz
~~~~~~~~~~

Oracle does not save the actual Timezone Name but UTC-Offsets. The
difference is subtle but can be potentially very nasty. Derick
Rethans explains it very well
`in a blog post of his <http://derickrethans.nl/storing-date-time-in-database.html>`_.

OCI8: SQL Queries with Question Marks
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

We had to implement a question mark to named parameter translation
inside the OCI8 DBAL Driver. It works as a very simple parser with two states: Inside Literal, Outside Literal.
From our perspective it should be working in all cases, but you have to be careful with certain
queries:

.. code-block:: sql

    SELECT * FROM users WHERE name = 'bar?'

Could in case of a bug with the parser be rewritten into:

.. code-block:: sql

    SELECT * FROM users WHERE name = 'bar:oci1'

For this reason you should always use prepared statements with
Oracle OCI8, never use string literals inside the queries. A query
for the user 'bar?' should look like:

.. code-block:: php

    $sql = 'SELECT * FROM users WHERE name = ?'
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, 'bar?');
    $stmt->execute();

OCI-LOB instances
~~~~~~~~~~~~~~~~~

Doctrine 2 always requests CLOB columns as strings, so that you as
a developer never get access to the ``OCI-LOB`` instance. Since we
are using prepared statements for all write operations inside the
ORM, using strings instead of the ``OCI-LOB`` does not cause any
problems.

Microsoft SQL Server
--------------------

Unique and NULL
~~~~~~~~~~~~~~~

Microsoft SQL Server takes Unique very seriously. There is only
ever one NULL allowed contrary to the standard where you can have
multiple NULLs in a unique column.

DateTime, DateTimeTz and Time Types
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

SQL Server has a variable return format for the datatype DATETIME(n)
if microseconds are allowed (n > 0). Whenever you save
a value with microseconds = 0.

If you do not let Doctrine create the date column types and rather
use types with microseconds you have replace the "DateTime",
"DateTimeTz" and "Time" types with a more liberal DateTime parser
that detects the format automatically:

::

    use Doctrine\DBAL\Types\Type;
    
    Type::overrideType('datetime', 'Doctrine\DBAL\Types\VarDateTime');
    Type::overrideType('datetimetz', 'Doctrine\DBAL\Types\VarDateTime');
    Type::overrideType('time', 'Doctrine\DBAL\Types\VarDateTime');
