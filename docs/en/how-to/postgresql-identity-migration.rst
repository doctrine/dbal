Migration to identity columns on PostgreSQL
===========================================

As of version 4, the DBAL uses identity columns to implement the ``autoincrement``
behavior on PostgreSQL instead of ``SERIAL*`` column types.

If you have a database with ``autoincrement`` columns created using DBAL 3 or earlier,
you will need to perform the following schema migration before being able to continue managing
the schema with the DBAL:

1. Identify all ``autoincrement`` columns and their tables.
2. Create the ``upgrade_serial_to_identity()`` function in the database as described in
   `PostgreSQL 10 identity columns explained <https://www.2ndquadrant.com/en/blog/postgresql-10-identity-columns/>`_:

    .. code-block:: sql

      CREATE OR REPLACE FUNCTION upgrade_serial_to_identity(tbl regclass, col name)
      RETURNS void
      LANGUAGE plpgsql
      AS $$
      DECLARE
        colnum smallint;
        seqid oid;
        count int;
      BEGIN
        -- find column number
        SELECT attnum INTO colnum FROM pg_attribute WHERE attrelid = tbl AND attname = col;
        IF NOT FOUND THEN
          RAISE EXCEPTION 'column does not exist';
        END IF;

        -- find sequence
           SELECT INTO seqid objid
          FROM pg_depend
          WHERE (refclassid, refobjid, refobjsubid) = ('pg_class'::regclass, tbl, colnum)
            AND classid = 'pg_class'::regclass AND objsubid = 0
            AND deptype = 'a';

        GET DIAGNOSTICS count = ROW_COUNT;
        IF count < 1 THEN
          RAISE EXCEPTION 'no linked sequence found';
        ELSIF count > 1 THEN
          RAISE EXCEPTION 'more than one linked sequence found';
        END IF;

        -- drop the default
        EXECUTE 'ALTER TABLE ' || tbl || ' ALTER COLUMN ' || quote_ident(col) || ' DROP DEFAULT';

        -- change the dependency between column and sequence to internal
        UPDATE pg_depend
          SET deptype = 'i'
          WHERE (classid, objid, objsubid) = ('pg_class'::regclass, seqid, 0)
            AND deptype = 'a';

        -- mark the column as identity column
        UPDATE pg_attribute
          SET attidentity = 'd'
          WHERE attrelid = tbl
            AND attname = col;
      END;
      $$;

3. Run the function for each table like this:

    .. code-block:: sql

      SELECT upgrade_serial_to_identity('article', 'id');

Without this migration, next time when DBAL 4 is used to manage the schema, it will perform a similar migration
but instead of reusing the existing sequence, it will drop it and create a new one. As a result,
all new sequence numbers will be generated from ``1``, which is most likely undesired.
