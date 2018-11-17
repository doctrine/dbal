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
a value with microseconds = 0, PostgreSQL will return this value in
the format:

::

    2010-10-10 10:10:10 (Y-m-d H:i:s)

However if you save a value with microseconds it will return the
full representation:

::

    2010-10-10 10:10:10.123456 (Y-m-d H:i:s.u)

Using the DateTime, DateTimeTz or Time type (and immutable variants) with microseconds
enabled columns can lead to errors because internally types expect
the exact format 'Y-m-d H:i:s' in combination with
``DateTime::createFromFormat()``. This method is twice as fast as
passing the date to the constructor of ``DateTime``.

This is why Doctrine always wants to create the time related types
without microseconds:

-  DateTime to ``TIMESTAMP(0) WITHOUT TIME ZONE``
-  DateTimeTz to ``TIMESTAMP(0) WITH TIME ZONE``
-  Time to ``TIME(0) WITHOUT TIME ZONE``

If you do not let Doctrine create the date column types and rather
use types with microseconds you have replace the "DateTime",
"DateTimeTz" and "Time" types (and immutable variants) with a more
liberal DateTime parser that detects the format automatically:

::

    use Doctrine\DBAL\Types\Type;

    Type::overrideType('datetime', 'Doctrine\DBAL\Types\VarDateTimeType');
    Type::overrideType('datetimetz', 'Doctrine\DBAL\Types\VarDateTimeType');
    Type::overrideType('time', 'Doctrine\DBAL\Types\VarDateTimeType');

    Type::overrideType('datetime_immutable', 'Doctrine\DBAL\Types\VarDateTimeImmutableType');
    Type::overrideType('datetimetz_immutable', 'Doctrine\DBAL\Types\VarDateTimeImmutableType');
    Type::overrideType('time_immutable', 'Doctrine\DBAL\Types\VarDateTimeImmutableType');

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
type therefore behaves like the DateTime type.

Sqlite
------

Buffered Queries and Isolation
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Be careful if you execute a ``SELECT`` query and do not iterate over the
statements results immediately. ``UPDATE`` statements executed before iteration
affect only the rows that have not been buffered into PHP memory yet. This
breaks the SERIALIZABLE transaction isolation property that SQLite supposedly
has.

DateTime
~~~~~~~~~~

Unlike most database management systems, Sqlite does not convert supplied
datetime strings to an internal storage format before storage. Instead, Sqlite
stores them as verbatim strings (i.e. as they are entered) and expects the user
to use the ``DATETIME()`` function when reading data which then converts the
stored values to datetime strings.
Because Doctrine is not using the ``DATETIME()`` function, you may end up with
"Could not convert database value ... to Doctrine Type datetime." exceptions
when trying to convert database values to ``\DateTime`` objects using

.. code-block:: php

    \Doctrine\DBAL\Types\Type::getType('datetime')->convertToPhpValue(...)

DateTimeTz
~~~~~~~~~~

Sqlite does not support saving timezones or offsets. The DateTimeTz
type therefore behaves like the DateTime type.

Reverse engineering primary key order
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
SQLite versions < 3.7.16 only return that a column is part of the primary key,
but not the order. This is only a problem with tables where the order of the
columns in the table is not the same as the order in the primary key. Tables
created with Doctrine use the order of the columns as defined in the primary
key.

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
"DateTimeTz" and "Time" types (and immutable variants) with a more
liberal DateTime parser that detects the format automatically:

::

    use Doctrine\DBAL\Types\Type;

    Type::overrideType('datetime', 'Doctrine\DBAL\Types\VarDateTime');
    Type::overrideType('datetimetz', 'Doctrine\DBAL\Types\VarDateTime');
    Type::overrideType('time', 'Doctrine\DBAL\Types\VarDateTime');

    Type::overrideType('datetime_immutable', 'Doctrine\DBAL\Types\VarDateTimeImmutableType');
    Type::overrideType('datetimetz_immutable', 'Doctrine\DBAL\Types\VarDateTimeImmutableType');
    Type::overrideType('time_immutable', 'Doctrine\DBAL\Types\VarDateTimeImmutableType');
