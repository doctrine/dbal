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
