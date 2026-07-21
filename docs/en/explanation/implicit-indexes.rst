Implicit indexes
================

Ever noticed the DBAL creating indexes you did not remember asking for,
with names such as ``IDX_885DBAFAA76ED395``? In this document, we will
distinguish three types of indexes:

user-defined indexes
    indexes you did ask for

DBAL-defined indexes
    indexes you did not ask for, created on your behalf by the DBAL

RDBMS-defined indexes
    indexes you did not ask for, created on your behalf by the RDBMS

RDBMS-defined indexes can be created by some database platforms when you
create a foreign key: they will create an index on the referencing
table, using the referencing columns.

The rationale behind this is that these indexes improve performance, for
instance for checking that a delete operation can be performed on a
referenced table without violating the constraint in the referencing
table.

Here are some database platforms that are known to create indexes when
creating a foreign key:

- `MySQL <https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html>`_
- `MariaDB <https://mariadb.com/kb/en/foreign-keys>`_

These platforms can drop an existing implicit index once it is fulfilled
by a newly created user-defined index.

Some other will not do so, on grounds that such indexes are not always
needed, and can be created in many different ways. They instead leave
that responsibility to the user:

- `PostgreSQL <https://stackoverflow.com/questions/970562/postgres-and-indexes-on-foreign-keys-and-primary-keys>`_
- `SQLite <https://sqlite.org/foreignkeys.html#fk_indexes>`_
- `SQL Server <https://stackoverflow.com/questions/836167/does-a-foreign-key-automatically-create-an-index>`_

So why does the DBAL create this index on every platform, even the ones
that neither require it nor create it themselves?

Because of how the DBAL compares schemas on MySQL and MariaDB. When the
DBAL reads a table back, it cannot tell an index you added from one the
database created for the foreign key: the metadata records nothing about
who created it. So a table you defined without a backing index comes
back with one. If the DBAL hadn't created it up-front, the comparison
would report a change that isn't real: the migration would drop the
index, the database would recreate it immediately, and the next
comparison would report the same change again — a migration that never
converges. Implicit indexes work around this limitation.

The DBAL's schema model is platform-agnostic, so it cannot apply this
only to MySQL and MariaDB. The index ends up on every database, including
the ones that never needed it.

This is a detail, but these indexes will be prefixed with ``IDX_``, and
typically look like this:

.. code-block:: sql

   CREATE INDEX IDX_885DBAFAA76ED395 ON posts (user_id)

The generated name fits within the platform's identifier-length limit.

In the case of MariaDB and MySQL, the creation of that DBAL-defined
index will result in the RDBMS-defined index being dropped.

You can still explicitly create such indexes yourself, and the DBAL will
notice when your index fulfills the indexing and constraint needs of the
implicit index it would create, and will refrain from doing so, much
like some platforms drop indexes that are redundant as explained above.
