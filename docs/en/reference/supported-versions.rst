Supported Versions
==================

This page documents the minimum database server versions required by the current
``4.x`` release series and by the upcoming ``5.0`` release.

Database server versions
------------------------

DBAL selects the most specific :doc:`platform <platforms>` for the server
version it connects to. The table below lists the oldest version supported for
each vendor; newer versions are supported as well.

.. list-table::
   :header-rows: 1
   :widths: 34 33 33

   * - Vendor
     - Minimum in 4.x
     - Minimum in 5.0
   * - MySQL
     - 5.7.9
     - 8.4
   * - MariaDB
     - 10.4.3
     - 10.11
   * - PostgreSQL
     - 10.0
     - 15
   * - Oracle
     - 18c (12.2.0.2)
     - 19c
   * - SQL Server
     - 2017
     - 2017
   * - Db2
     - 11.5
     - 11.5
   * - SQLite
     - 3.37.2
     - 3.37.2

DBAL never actively prevents you from connecting to an older version. If you do,
it falls back to the closest available platform; the connection will likely
work, but correct behavior is not guaranteed and such versions are not covered
by the test suite.

The SQLite version is usually determined by the one your operating system
provides. The minimum above is the version shipped with Ubuntu 22.04 LTS, the
oldest operating system DBAL is tested against.

Where the ``5.0`` minimum is higher than the ``4.x`` minimum, a version below the
``5.0`` minimum still works in ``4.x`` but will no longer be supported in
``5.0``. Upgrade your database server before migrating to the next major release.
Some of these versions already emit a deprecation notice in ``4.x``, which you
can surface in your test suite by configuring the
`doctrine/deprecations <https://github.com/doctrine/deprecations>`_ library to
collect or throw on deprecations.

See also
--------

-  :doc:`platforms` for the list of platform classes that map to each vendor
   version.
