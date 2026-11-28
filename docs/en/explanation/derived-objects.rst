Derived schema objects
======================

Besides the objects of the application's schema, the database may report
objects that exist because of them:

- the index that the database may create to *back* a primary key or unique
  constraint, which means to implement the constraint;
- a unique constraint that the database reports for a unique index.

The DBAL calls such an object *derived*, and an object of the application's
schema *declared*. ``Index::getOrigin()`` and ``UniqueConstraint::getOrigin()``
tell them apart.

How the DBAL treats derived objects
-----------------------------------

The DBAL emits DDL only for declared objects. The database maintains the
derived ones itself.

Only introspection produces derived objects. An index or unique constraint that
the application creates or changes is declared. Renaming a column changes the
column, not the objects that cover it, so a derived object that covers the
column stays derived.
