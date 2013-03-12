Portability
===========

There are often cases when you need to write an application or library that is portable
across multiple different database vendors. The Doctrine ORM is one example of such
a library. It is an abstraction layer over all the currently supported vendors (MySQL, Oracle,
PostgreSQL, SQLite and Microsoft SQL Server). If you want to use the DBAL to write a portable application
or library you have to follow lots of rules to make all the different vendors work the
same.

There are many different layers that you need to take care of, here is a quick list:

1.  Returning of data is handled differently across vendors.
    Oracle converts empty strings to NULL, which means a portable application
    needs to convert all empty strings to null.
2.  Additionally some vendors pad CHAR columns to their length, whereas others don't.
    This means all strings returned from a database have to be passed through ``rtrim()``.
3.  Case-sensitivity of column keys is handled differently in all databases, even depending
    on identifier quoting or not. You either need to know all the rules or fix the cases
    to lower/upper-case only.
4.  ANSI-SQL is not implemented fully by the different vendors. You have to make
    sure that the SQL you write is supported by all the vendors you are targeting.
5.  Some vendors use sequences for identity generation, some auto-increment approaches.
    Both are completely different (pre- and post-insert access) and therefore need
    special handling.
6.  Every vendor has a list of keywords that are not allowed inside SQL. Some even
    allow a subset of their keywords, but not at every position.
7.  Database types like dates, long text fields, booleans and many others are handled
    very differently between the vendors.
8.  There are differences with the regard to support of positional, named or both styles of parameters
    in prepared statements between all vendors.

For each point in this list there are different abstraction layers in Doctrine DBAL that you
can use to write a portable application.

Connection Wrapper
------------------

This functionality is only implemented with Doctrine 2.1 upwards.

To handle all the points 1-3 you have to use a special wrapper around the database
connection. The handling and differences to tackle are all taken from the great 
`PEAR MDB2 library <http://pear.php.net/package/MDB2/redirected>`_.

Using the following code block in your initialization will:

* ``rtrim()`` all strings if necessary
* Convert all empty strings to null
* Return all associative keys in lower-case, using PDO native functionality or implemented in PHP userland (OCI8).

.. code-block:: php

    <?php
    $params = array(
        // vendor specific configuration
        //...
        'wrapperClass' => 'Doctrine\DBAL\Portability\Connection',
        'portability' => \Doctrine\DBAL\Portability\Connection::PORTABILITY_ALL,
        'fetch_case' => \PDO::CASE_LOWER,
    );

This sort of portability handling is pretty expensive because all the result
rows and columns have to be looped inside PHP before being returned to you.
This is why by default Doctrine ORM does not use this compability wrapper but
implements another approach to handle assoc-key casing and ignores the other
two issues.

Database Platform
-----------------

Using the database platform you can generate bits of SQL for you, specifically
in the area of SQL functions to achieve portability. You should have a look
at all the different methods that the platforms allow you to access.

Keyword Lists
-------------

This functionality is only implemented with Doctrine 2.1 upwards.

Doctrine ships with lists of keywords for every supported vendor. You
can access a keyword list through the schema manager of the vendor you
are currently using or just instantiating it from the ``Doctrine\DBAL\Platforms\Keywords``
namespace.