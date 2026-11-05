Supported Versions
==================

This page documents the minimum database server versions required by the current
``5.x`` release series.

Database server versions
------------------------

DBAL selects the most specific :doc:`platform <platforms>` for the server
version it connects to. The table below lists the oldest version supported for
each vendor; newer versions are supported as well.

.. list-table::
   :header-rows: 1
   :widths: 50 50

   * - Vendor
     - Minimum in 5.x
   * - MySQL
     - 8.4
   * - MariaDB
     - 10.11
   * - PostgreSQL
     - 15
   * - Oracle
     - 19c
   * - SQL Server
     - 2019
   * - Db2
     - 11.5
   * - SQLite
     - 3.37.2

DBAL never actively prevents you from connecting to an older version. If you do,
it falls back to the closest available platform; the connection will likely
work, but correct behavior is not guaranteed and such versions are not covered
by the test suite.

The SQLite version is usually determined by the one your operating system
provides. The minimum above is the version shipped with Ubuntu 22.04 LTS, the
oldest operating system DBAL is tested against.

See also
--------

-  :doc:`platforms` for the list of platform classes that map to each vendor
   version.
