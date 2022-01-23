Doctrine SQL comments
=====================

In some occasions DBAL generates ``DC2Type`` SQL comments in columns of
the databases schemas that is maintained by Doctrine. These comments
have a functional purpose in DBAL.

``DC2`` is a shorthand for Doctrine 2, as opposed to `Doctrine 1
<https://github.com/doctrine/doctrine1>`_,
an ancestor that relied on `the active record pattern
<https://en.wikipedia.org/wiki/Active_record_pattern>`_.

These comments are here to help with reverse engineering. Inside the
DBAL, the schema manager can leverage them to resolve ambiguities when
it comes to determining the correct DBAL type for a given column.

For instance: You are following a `Database First approach
<https://www.doctrine-project.org/projects/doctrine-orm/en/current/tutorials/getting-started-database.html>`_,
and want to use GUIDs in your application while using a platform that does not have a native type for
this mapping.
By commenting columns that hold GUIDs with ``(DC2Type:guid)``, you can
let the DBAL know it is supposed to use ``Doctrine\DBAL\Types\GuidType``
when dealing with that column.
When using reverse engineering tools, this can be used to generate
accurate information.
For instance, if you use Doctrine ORM, there is `a reverse engineering example
<https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/tools.html#reverse-engineering>`_
to show how to generate a proper mapping.

A table with such a column may have a declaration that looks as follows:

.. code-block:: sql

   CREATE TABLE "movies" (
        uuid CHAR(36) NOT NULL, --(DC2Type:guid)
        title varchar(255) NOT NULL
        …,
        PRIMARY KEY(uuid)
   )

In the past, these comments were also useful to avoid false positives
when diffing a schema created with the DBAL API with a schema
introspected from the database. Since `platform-aware comparison was
introduced in 3.2.0
<https://www.doctrine-project.org/2021/11/26/dbal-3.2.0.html>`_, this is
no longer the case. They must be kept in order to keep the
platform-unaware comparison APIs working though.

It is important to note that these comments are an implementation detail
of the DBAL and should not be relied upon by application code. They are
removed in DBAL 4.0.
