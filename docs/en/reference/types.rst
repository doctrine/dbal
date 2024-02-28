Types
=====

Besides abstraction of SQL one needs a translation between database
and PHP data-types to implement database independent applications.
Doctrine DBAL has a type translation system baked in that supports the
conversion from and to PHP values from any database platform,
as well as platform independent SQL generation for any Doctrine
Type.

Using the ORM you generally don't need to know about the Type
system. This is unless you want to make use of database vendor
specific database types not included in Doctrine DBAL.

Types are flyweights. This means there is only ever one instance of
a type and it is not allowed to contain any state. Creation of type
instances is abstracted through a static get method
``Doctrine\DBAL\Types\Type::getType()``.

Types are abstracted across all the supported database
vendors.

Reference
---------

The following chapter gives an overview of all available Doctrine DBAL
types with short explanations on their context and usage.
The type names listed here equal those that can be passed to the
``Doctrine\DBAL\Types\Type::getType()``  factory method in order to retrieve
the desired type instance.

.. code-block:: php

    <?php

    // Returns instance of \Doctrine\DBAL\Types\IntegerType
    $type = \Doctrine\DBAL\Types\Type::getType('integer');

Numeric types
~~~~~~~~~~~~~

Types that map numeric data such as integers, fixed and floating point
numbers.

Integer types
^^^^^^^^^^^^^

Types that map numeric data without fractions.

smallint
++++++++

Maps and converts 2-byte integer values.
Unsigned integer values have a range of **0** to **65535** while signed
integer values have a range of **−32768** to **32767**.
If you know the integer data you want to store always fits into one of these ranges
you should consider using this type.
Values retrieved from the database are always converted to PHP's ``integer`` type
or ``null`` if no data is present.

.. note::

    Not all of the database vendors support unsigned integers, so such an assumption
    might not be propagated to the database.

integer
+++++++

Maps and converts 4-byte integer values.
Unsigned integer values have a range of **0** to **4294967295** while signed
integer values have a range of **−2147483648** to **2147483647**.
If you know the integer data you want to store always fits into one of these ranges
you should consider using this type.
Values retrieved from the database are always converted to PHP's ``integer`` type
or ``null`` if no data is present.

.. note::

    Not all of the database vendors support unsigned integers, so such an assumption
    might not be propagated to the database.

bigint
++++++

Maps and converts 8-byte integer values.
Unsigned integer values have a range of **0** to **18446744073709551615**, while signed
integer values have a range of **−9223372036854775808** to **9223372036854775807**.
If you know the integer data you want to store always fits into one of these ranges
you should consider using this type.
Values retrieved from the database are always converted to PHP's ``integer`` type
if they are within PHP's integer range or ``string`` if they aren't.
Otherwise, returns ``null`` if no data is present.

.. note::

    Due to architectural differences, 32-bit PHP systems have a smaller
    integer range than their 64-bit counterparts. On 32-bit systems,
    values exceeding this range will be represented as strings instead
    of integers. Bear in mind that not all database vendors
    support unsigned integers, so schema configuration cannot be
    enforced.

Decimal types
^^^^^^^^^^^^^

Types that map numeric data with fractions.

decimal
+++++++

Maps and converts numeric data with fixed-point precision.
If you need an exact precision for numbers with fractions, you should consider using
this type.
Values retrieved from the database are always converted to PHP's ``string`` type
or ``null`` if no data is present.

.. note::

    For compatibility reasons this type is not converted to a double
    as PHP can only preserve the precision to a certain degree. Otherwise
    it approximates precision which can lead to false assumptions in
    applications.

float
+++++

Maps and converts numeric data with floating-point precision.
If you only need an approximate precision for numbers with fractions, you should
consider using this type.
Values retrieved from the database are always converted to PHP's
``float``/``double`` type or ``null`` if no data is present.

String types
~~~~~~~~~~~~

Types that map string data such as character and binary text.

Character string types
^^^^^^^^^^^^^^^^^^^^^^

Types that map string data of letters, numbers, and other symbols.

string
++++++

Maps and converts string data with a maximum length.
If you know that the data to be stored always fits into the specified length,
you should consider using this type.
Values retrieved from the database are always converted to PHP's string type
or ``null`` if no data is present.

.. note::

    Database vendors have different limits for the maximum length of a
    varying string. Doctrine internally maps the ``string`` type to the
    vendor's ``text`` type if the maximum allowed length is exceeded.
    This can lead to type inconsistencies when reverse engineering the
    type from the database.

ascii_string
++++++++++++

Similar to the ``string`` type but for binding non-unicode data. This type
should be used with database vendors where a binding type mismatch
can trigger an implicit cast and lead to performance problems.

text
++++

Maps and converts string data without a maximum length.
If you don't know the maximum length of the data to be stored, you should
consider using this type.
Values retrieved from the database are always converted to PHP's ``string`` type
or ``null`` if no data is present.

guid
++++

Maps and converts a "Globally Unique Identifier".
If you want to store a GUID, you should consider using this type, as some
database vendors have a native data type for this kind of data which offers
the most efficient way to store it. For vendors that do not support this
type natively, this type is mapped to the ``string`` type internally.
Values retrieved from the database are always converted to PHP's ``string`` type
or ``null`` if no data is present.

Binary string types
^^^^^^^^^^^^^^^^^^^

Types that map binary string data including images and other types of
information that are not interpreted by the database.
If you know that the data to be stored always is in binary format, you
should consider using one of these types in favour of character string
types, as it offers the most efficient way to store it.

binary
++++++

Maps and converts binary string data with a maximum length.
If you know that the data to be stored always fits into the specified length,
you should consider using this type.
Values retrieved from the database are always converted to PHP's ``resource`` type
or ``null`` if no data is present.

.. note::

    Database vendors have different limits for the maximum length of a
    varying binary string. Doctrine internally maps the ``binary`` type to the
    vendor's ``blob`` type if the maximum allowed length is exceeded.
    This can lead to type inconsistencies when reverse engineering the
    type from the database.

blob
++++

Maps and converts binary string data without a maximum length.
If you don't know the maximum length of the data to be stored, you should
consider using this type.
Values retrieved from the database are always converted to PHP's ``resource`` type
or ``null`` if no data is present.

Bit types
~~~~~~~~~

Types that map bit data such as boolean values.

boolean
^^^^^^^

Maps and converts boolean data.
If you know that the data to be stored always is a ``boolean`` (``true`` or ``false``),
you should consider using this type.
Values retrieved from the database are always converted to PHP's ``boolean`` type
or ``null`` if no data is present.

.. note::

    As most of the database vendors do not have a native boolean type,
    this type silently falls back to the smallest possible integer or
    bit data type if necessary to ensure the least possible data storage
    requirements are met.

Date and time types
~~~~~~~~~~~~~~~~~~~

Types that map date, time and timezone related values such as date only,
date and time, date, time and timezone or time only.

date
^^^^

Maps and converts date data without time and timezone information.
If you know that the data to be stored always only needs to be a date
without time and timezone information, you should consider using this type.
Values retrieved from the database are always converted to PHP's ``\DateTime`` object
or ``null`` if no data is present.

date_immutable
^^^^^^^^^^^^^^

The immutable variant of the ``date`` type.
Values retrieved from the database are always converted to PHP's ``\DateTimeImmutable``
object or ``null`` if no data is present.

datetime
^^^^^^^^

Maps and converts date and time data without timezone information.
If you know that the data to be stored always only needs to be a date
with time but without timezone information, you should consider using this type.
Values retrieved from the database are always converted to PHP's ``\DateTime`` object
or ``null`` if no data is present.

.. warning::

    Before 2.5 this type always required a specific format,
    defined in ``$platform->getDateTimeFormatString()``, which
    could cause quite some troubles on platforms that had various
    microtime precision formats.
    Starting with 2.5 whenever the parsing of a date fails with
    the predefined platform format, ``DateTime::__construct()``
    method will be used to parse the date.

    This could cause some troubles when your date format is weird
    and not parsed correctly by ``DateTime::__construct()``, however since
    databases are rather strict on dates there should be no problem.

.. warning::

    Passing instances of ``DateTimeImmutable`` to this type is deprecated since 3.7. Use
    :ref:`datetime_immutable` instead.

.. _datetime_immutable:
datetime_immutable
^^^^^^^^^^^^^^^^^^

The immutable variant of the ``datetime`` type.
Values retrieved from the database are always converted to PHP's ``\DateTimeImmutable``
object or ``null`` if no data is present.

datetimetz
^^^^^^^^^^

Maps and converts date with time and timezone information data.
If you know that the data to be stored always contains date, time and timezone
information, you should consider using this type.
Values retrieved from the database are always converted to PHP's ``\DateTime`` object
or ``null`` if no data is present.

.. warning::

    Passing instances of ``DateTimeImmutable`` to this type is deprecated since 3.7. Use
    :ref:`datetimetz_immutable` instead.

.. _datetimetz_immutable:
datetimetz_immutable
^^^^^^^^^^^^^^^^^^^^

The immutable variant of the ``datetimetz`` type.
Values retrieved from the database are always converted to PHP's ``\DateTimeImmutable``
object or ``null`` if no data is present.

time
^^^^

Maps and converts time data without date and timezone information.
If you know that the data to be stored only needs to be a time
without date, time and timezone information, you should consider using this type.
Values retrieved from the database are always converted to PHP's ``\DateTime`` object
or ``null`` if no data is present.

time_immutable
^^^^^^^^^^^^^^

The immutable variant of the ``time`` type.
Values retrieved from the database are always converted to PHP's ``\DateTimeImmutable``
object or ``null`` if no data is present.

dateinterval
^^^^^^^^^^^^

Maps and converts date and time difference data without timezone information.
If you know that the data to be stored is the difference between two date and time values,
you should consider using this type.
Values retrieved from the database are always converted to PHP's ``\DateInterval`` object
or ``null`` if no data is present.

.. note::

    See the Known Vendor Issue :doc:`known-vendor-issues` section
    for details about the different handling of microseconds and
    timezones across all the different vendors.

.. warning::

    All date types assume that you are exclusively using the default timezone
    set by `date_default_timezone_set() <http://docs.php.net/manual/en/function.date-default-timezone-set.php>`_
    or by the php.ini configuration ``date.timezone``.

    If you need specific timezone handling you have to handle this
    in your domain, converting all the values back and forth from UTC.

Array types
~~~~~~~~~~~

Types that map array data in different variations such as simple arrays,
real arrays or JSON format arrays.

array
^^^^^

Maps and converts array data based on PHP serialization.
If you need to store an exact representation of your array data,
you should consider using this type as it uses serialization
to represent an exact copy of your array as string in the database.
Values retrieved from the database are always converted to PHP's ``array`` type
using deserialization or ``null`` if no data is present.

.. note::

    This type will always be mapped to the database vendor's ``text`` type
    internally as there is no way of storing a PHP array representation
    natively in the database.
    Furthermore this type requires an SQL column comment hint so that it can be
    reverse engineered from the database. Doctrine cannot map back this type
    properly on vendors not supporting column comments and will fall back to
    ``text`` type instead.

.. warning::

    This type is deprecated since 3.4.0, use :ref:`json` instead.

simple_array
^^^^^^^^^^^^

Maps and converts array data based on PHP comma delimited imploding and exploding.
If you know that the data to be stored always is a scalar value based one-dimensional
array, you should consider using this type as it uses simple PHP imploding and
exploding techniques to serialize and deserialize your data.
Values retrieved from the database are always converted to PHP's ``array`` type
using comma delimited ``explode()`` or ``null`` if no data is present.

.. note::

    This type will always be mapped to the database vendor's ``text`` type
    internally as there is no way of storing a PHP array representation
    natively in the database.
    Furthermore this type requires an SQL column comment hint so that it can be
    reverse engineered from the database. Doctrine cannot map back this type
    properly on vendors not supporting column comments and will fall back to
    ``text`` type instead.

.. warning::

    You should never rely on a specific PHP type like ``boolean``,
    ``integer``, ``float`` or ``null`` when retrieving values from
    the database as the ``explode()`` deserialization technique used
    by this type converts every single array item to ``string``.
    This basically means that every array item other than ``string``
    will lose its type awareness.

.. _json:
json
^^^^

Maps and converts array data based on PHP's JSON encoding functions.
If you know that the data to be stored always is in a valid UTF-8
encoded JSON format string, you should consider using this type.
Values retrieved from the database are always converted to PHP's
native types using PHP's ``json_decode()`` function.
JSON objects are always converted to PHP associative arrays.

.. note::

    The ``json`` type doesn't preserve the type of PHP objects.
    PHP objects will always be encoded as (anonymous) JSON objects.
    JSON objects will always be decoded as PHP associative arrays.

    To preserve the type of PHP objects, consider using
    `Doctrine JSON ODM <https://github.com/dunglas/doctrine-json-odm>`_.

.. note::

    Some vendors have a native JSON type and Doctrine will use it if possible
    and otherwise silently fall back to the vendor's ``text`` type to ensure
    the most efficient storage requirements.
    If the vendor does not have a native JSON type, this type requires an SQL
    column comment hint so that it can be reverse engineered from the database.
    Doctrine cannot map back this type properly on vendors not supporting column
    comments and will fall back to ``text`` type instead.

.. warning::

    You should never rely on the order of your JSON object keys, as some vendors
    like MySQL sort the keys of its native JSON type using an internal order
    which is also subject to change.

Object types
~~~~~~~~~~~~

Types that map to objects such as POPOs.

object
^^^^^^

Maps and converts object data based on PHP serialization.
If you need to store an exact representation of your object data,
you should consider using this type as it uses serialization
to represent an exact copy of your object as string in the database.
Values retrieved from the database are always converted to PHP's ``object`` type
using deserialization or ``null`` if no data is present.

.. note::

    This type will always be mapped to the database vendor's ``text`` type
    internally as there is no way of storing a PHP object representation
    natively in the database.
    Furthermore this type requires an SQL column comment hint so that it can be
    reverse engineered from the database. Doctrine cannot map back this type
    properly on vendors not supporting column comments and will fall back to
    ``text`` type instead.

.. warning::

    While the built-in ``text`` type of MySQL and MariaDB can store binary data,
    ``mysqldump`` cannot properly export ``text`` fields containing binary data.
    This will cause creating and restoring of backups fail silently. A workaround is
    to ``serialize()``/``unserialize()`` and ``base64_encode()``/``base64_decode()``
    PHP objects and store them into a ``text`` field manually.

.. warning::

    Because the built-in ``text`` type of PostgreSQL does not support NULL bytes,
    the object type will cause deserialization errors on PostgreSQL. A workaround is
    to ``serialize()``/``unserialize()`` and ``base64_encode()``/``base64_decode()`` PHP objects and store
    them into a ``text`` field manually.

.. warning::

    This type is deprecated since 3.4.0, use :ref:`json` instead.

.. _mappingMatrix:

Mapping Matrix
--------------

The following table shows an overview of Doctrine's type abstraction.
The matrix contains the mapping information for how a specific Doctrine
type is mapped to the database and back to PHP.
Please also notice the mapping specific footnotes for additional information.
::

    +-------------------+---------------+-----------------------------------------------------------------------------------------------+
    | Doctrine          | PHP           | Database vendor                                                                               |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | Name                     | Version | Type                                                     |
    +===================+===============+==========================+=========+==========================================================+
    | **smallint**      | ``integer``   | **MySQL**                | *all*   | ``SMALLINT`` ``UNSIGNED`` [10]  ``AUTO_INCREMENT`` [11]  |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``SMALLINT``                                             |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **Oracle**               | *all*   | ``NUMBER(5)``                                            |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``SMALLINT`` ``IDENTITY`` [11]                           |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQLite**               | *all*   | ``INTEGER`` [15]                                         |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **integer**       | ``integer``   | **MySQL**                | *all*   | ``INT`` ``UNSIGNED`` [10]  ``AUTO_INCREMENT`` [11]       |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``INT`` [12]                                             |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``SERIAL`` [11]                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **Oracle**               | *all*   | ``NUMBER(10)``                                           |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``INT`` ``IDENTITY`` [11]                                |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQLite**               | *all*   | ``INTEGER`` [15]                                         |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **bigint**        | ``string``    | **MySQL**                | *all*   | ``BIGINT`` ``UNSIGNED`` [10]  ``AUTO_INCREMENT`` [11]    |
    |                   | [8]           +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``BIGINT`` [12]                                          |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``BIGSERIAL`` [11]                                       |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **Oracle**               | *all*   | ``NUMBER(20)``                                           |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``BIGINT`` ``IDENTITY`` [11]                             |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQLite**               | *all*   | ``INTEGER`` [15]                                         |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **decimal** [7]   | ``string``    | **MySQL**                | *all*   | ``NUMERIC(p, s)`` ``UNSIGNED`` [10]                      |
    |                   | [9]           +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``NUMERIC(p, s)``                                        |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **Oracle**               |         |                                                          |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQL Server**           |         |                                                          |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **float**         | ``float``     | **MySQL**                | *all*   | ``DOUBLE PRECISION`` ``UNSIGNED`` [10]                   |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``DOUBLE PRECISION``                                     |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **Oracle**               |         |                                                          |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQL Server**           |         |                                                          |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **string**        | ``string``    | **MySQL**                | *all*   | ``VARCHAR(n)`` [3]                                       |
    | [2]  [5]          |               +--------------------------+         |                                                          |
    |                   |               | **PostgreSQL**           |         |                                                          |
    |                   |               +--------------------------+         +----------------------------------------------------------+
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **Oracle**               | *all*   | ``VARCHAR2(n)`` [3]                                      |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``CHAR(n)`` [4]                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``NVARCHAR(n)`` [3]                                      |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``NCHAR(n)`` [4]                                         |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **ascii_string**  | ``string``    | **SQL Server**           |         | ``VARCHAR(n)``                                           |
    |                   |               |                          |         | ``CHAR(n)``                                              |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **text**          | ``string``    | **MySQL**                | *all*   | ``TINYTEXT`` [16]                                        |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``TEXT`` [17]                                            |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``MEDIUMTEXT`` [18]                                      |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``LONGTEXT`` [19]                                        |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``TEXT``                                                 |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **Oracle**               | *all*   | ``CLOB``                                                 |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``VARCHAR(MAX)``                                         |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **guid**          | ``string``    | **MySQL**                | *all*   | ``CHAR(36)`` [1]                                         |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **Oracle**               |         |                                                          |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``UNIQUEIDENTIFIER``                                     |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **PostgreSQL**           | *all*   | ``UUID``                                                 |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **binary**        | ``resource``  | **MySQL**                | *all*   | ``VARBINARY(n)`` [3]                                     |
    | [2]  [6]          |               +--------------------------+         |                                                          |
    |                   |               | **SQL Server**           |         +----------------------------------------------------------+
    |                   |               +--------------------------+         | ``BINARY(n)`` [4]                                        |
    |                   |               | **Oracle**               | *all*   | ``RAW(n)``                                               |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``BYTEA`` [15]                                           |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQLite**               | *all*   | ``BLOB`` [15]                                            |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **blob**          | ``resource``  | **MySQL**                | *all*   | ``TINYBLOB`` [16]                                        |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``BLOB`` [17]                                            |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``MEDIUMBLOB`` [18]                                      |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``LONGBLOB`` [19]                                        |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **Oracle**               | *all*   | ``BLOB``                                                 |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``VARBINARY(MAX)``                                       |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``BYTEA``                                                |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **boolean**       | ``boolean``   | **MySQL**                | *all*   | ``TINYINT(1)``                                           |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``BOOLEAN``                                              |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``BIT``                                                  |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **Oracle**               | *all*   | ``NUMBER(1)``                                            |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **date**          | ``\DateTime`` | **MySQL**                | *all*   | ``DATE``                                                 |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **PostgreSQL**           |         |                                                          |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **Oracle**               |         |                                                          |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+                                                          |
    |                   |               | **SQL Server**           | "all"   |                                                          |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **datetime**      | ``\DateTime`` | **MySQL**                | *all*   | ``DATETIME`` [13]                                        |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``DATETIME``                                             |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``TIMESTAMP(0) WITHOUT TIME ZONE``                       |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **Oracle**               | *all*   | ``TIMESTAMP(0)``                                         |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **datetimetz**    | ``\DateTime`` | **MySQL**                | *all*   | ``DATETIME``  [14]  [15]                                 |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+                                                          |
    |                   |               | **SQL Server**           | "all"   |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``TIMESTAMP(0) WITH TIME ZONE``                          |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **Oracle**               |         |                                                          |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **time**          | ``\DateTime`` | **MySQL**                | *all*   | ``TIME``                                                 |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``TIME(0) WITHOUT TIME ZONE``                            |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **Oracle**               | *all*   | ``DATE`` [15]                                            |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | "all"   | ``TIME(0)``                                              |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **array** [1]     | ``array``     | **MySQL**                | *all*   | ``TINYTEXT`` [16]                                        |
    +-------------------+               |                          |         +----------------------------------------------------------+
    | **simple array**  |               |                          |         | ``TEXT`` [17]                                            |
    | [1]               |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``MEDIUMTEXT`` [18]                                      |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``LONGTEXT`` [19]                                        |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``TEXT``                                                 |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **Oracle**               | *all*   | ``CLOB``                                                 |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``VARCHAR(MAX)``                                         |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **json**          | ``mixed``     | **MySQL**                | *all*   | ``JSON``                                                 |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``JSON`` [20]                                            |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``JSONB`` [21]                                           |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **Oracle**               | *all*   | ``CLOB`` [1]                                             |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``VARCHAR(MAX)`` [1]                                     |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+
    | **object** [1]    | ``object``    | **MySQL**                | *all*   | ``TINYTEXT`` [16]                                        |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``TEXT`` [17]                                            |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``MEDIUMTEXT`` [18]                                      |
    |                   |               |                          |         +----------------------------------------------------------+
    |                   |               |                          |         | ``LONGTEXT`` [19]                                        |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **PostgreSQL**           | *all*   | ``TEXT``                                                 |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **Oracle**               | *all*   | ``CLOB``                                                 |
    |                   |               +--------------------------+         |                                                          |
    |                   |               | **SQLite**               |         |                                                          |
    |                   |               +--------------------------+---------+----------------------------------------------------------+
    |                   |               | **SQL Server**           | *all*   | ``VARCHAR(MAX)``                                         |
    +-------------------+---------------+--------------------------+---------+----------------------------------------------------------+

**Notes**

* [1] Requires hint in the column comment for proper reverse engineering of the appropriate
  Doctrine type mapping.
* [2] **n** is the **length** attribute set in the column definition (defaults to 255 if omitted).
* [3] Chosen if the column definition has the **fixed** attribute set to ``false`` (default).
* [4] Chosen if the column definition has the **fixed** attribute set to ``true``.
* [5] Silently maps to the vendor specific ``text`` type if the given **length** attribute for
  **n** exceeds the maximum length the related platform allows. If this is the case, please
  see [15] .
* [6] Silently maps to the vendor specific ``blob`` type if the given **length** attribute for
  **n** exceeds the maximum length the related platform allows. If this is the case, please
  see [15] .
* [7] **p** is the precision and **s** the scale set in the column definition.
  The precision defaults to ``10`` and the scale to ``0`` if not set.
* [8] Returns PHP ``string`` type value instead of ``integer`` because of maximum integer value
  implications on non 64bit platforms.
* [9] Returns PHP ``string`` type value instead of ``double`` because of PHP's limitation in
  preserving the exact precision when casting to ``double``.
* [10] Used if **unsigned** attribute is set to ``true`` in the column definition (default ``false``).
* [11] Used if **autoincrement** attribute is set to ``true`` in the column definition (default ``false``).
* [12] Chosen if the column definition has the **autoincrement** attribute set to ``false`` (default).
* [13] Chosen if the column definition does not contain the **version** option inside the **platformOptions**
  attribute array or is set to ``false`` which marks it as a non-locking information column.
* [14] Fallback type as the vendor does not support a native date time type with timezone information.
  This means that the timezone information gets lost when storing a value.
* [15] Cannot be safely reverse engineered to the same Doctrine type as the vendor does not have a
  native distinct data type for this mapping. Using this type with this vendor can therefore
  have implications on schema comparison (*online* vs *offline* schema) and PHP type safety
  (data conversion from database to PHP value) because it silently falls back to its
  appropriate Doctrine type.
* [16] Chosen if the column length is less or equal to **2 ^  8 - 1 = 255**.
* [17] Chosen if the column length is less or equal to **2 ^ 16 - 1 = 65535**.
* [18] Chosen if the column length is less or equal to **2 ^ 24 - 1 = 16777215**.
* [19] Chosen if the column length is less or equal to **2 ^ 32 - 1 = 4294967295** or empty.
* [20] Chosen if the column definition does not contain the **jsonb** option inside the **platformOptions**
  attribute array or is set to ``false``.
* [21] Chosen if the column definition contains the **jsonb** option inside the **platformOptions**
  attribute array and is set to ``true``.

Detection of Database Types
---------------------------

When calling table inspection methods on your connections
``SchemaManager`` instance the retrieved database column types are
translated into Doctrine mapping types. Translation is necessary to
allow database abstraction and metadata comparisons for example for
Migrations or the ORM SchemaTool.

Each database platform has a default mapping of database types to
Doctrine types. You can inspect this mapping for platform of your
choice looking at the
``AbstractPlatform::initializeDoctrineTypeMappings()``
implementation.

If you want to change how Doctrine maps a database type to a
``Doctrine\DBAL\Types\Type`` instance you can use the
``AbstractPlatform::registerDoctrineTypeMapping($dbType, $doctrineType)``
method to add new database types or overwrite existing ones.

.. note::

    You can only map a database type to exactly one Doctrine type.
    Database vendors that allow to define custom types like PostgreSQL
    can help to overcome this issue.

Custom Mapping Types
--------------------

Just redefining how database types are mapped to all the existing
Doctrine types is not at all that useful. You can define your own
Doctrine Mapping Types by extending ``Doctrine\DBAL\Types\Type``.
You are required to implement 4 different methods to get this
working.

See this example of how to implement a Money object in PostgreSQL.
For this we create the type in PostgreSQL as:

.. code-block:: sql

    CREATE DOMAIN MyMoney AS DECIMAL(18,3);

Now we implement our ``Doctrine\DBAL\Types\Type`` instance:

::

    <?php
    namespace My\Project\Types;

    use Doctrine\DBAL\Types\Type;
    use Doctrine\DBAL\Platforms\AbstractPlatform;

    /**
     * My custom datatype.
     */
    class MoneyType extends Type
    {
        public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
        {
            return 'MyMoney';
        }

        public function convertToPHPValue($value, AbstractPlatform $platform)
        {
            return new Money($value);
        }

        public function convertToDatabaseValue($value, AbstractPlatform $platform)
        {
            return $value->toDecimal();
        }
    }

The job of Doctrine-DBAL is to transform your type into an SQL
declaration. You can modify the SQL declaration Doctrine will produce.
At first, you override the ``convertToPhpValueSQL`` and
``convertToDatabaseValueSQL`` methods:

::

    <?php
    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        return 'MyMoneyFunction(\''.$sqlExpr.'\') ';
    }

    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return 'MyFunction('.$sqlExpr.')';
    }

Then you have to register this type with the Doctrine Type system and
hook it into the database platform:

::

    <?php
    Type::addType('money', 'My\Project\Types\MoneyType');
    $conn->getDatabasePlatform()->registerDoctrineTypeMapping('MyMoney', 'money');

This would allow using a money type in the ORM for example and
have Doctrine automatically convert it back and forth to the
database.

It is also possible to register type instances directly, in case you
need to pass parameters to your instance::

    <?php
    namespace My\Project\Types;

    use Doctrine\DBAL\Types\Type;
    use Doctrine\DBAL\Platforms\AbstractPlatform;

    final class StringReplacingType extends StringType
    {
        /**
         * @param array<string, string> $replacements
         */
        public function __construct(
            private array $replacements,
        ) {
        }

        public function convertToDatabaseValue($value, AbstractPlatform $platform): string
        {
            return strtr($value, $this->replacements);
        }
    }

To do that, you can obtain the ``TypeRegistry`` singleton from ``Type``
and register your type in it::

    <?php
    Type::getTypeRegistry()->register('emojifyingType', new StringReplacingType(
        [
            ':)' => '😊',
            ':(' => '😞',
            ':D' => '😄',
            ':P' => '😛',
        ]
    ));
