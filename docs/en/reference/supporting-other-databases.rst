Supporting Other Databases
==========================

To support a database which is not currently shipped with Doctrine
you have to implement the following interfaces and abstract
classes:

-  ``\Doctrine\DBAL\Driver\Connection``
-  ``\Doctrine\DBAL\Driver\Statement``
-  ``\Doctrine\DBAL\Driver``
-  ``\Doctrine\DBAL\Platforms\AbstractPlatform``
-  ``\Doctrine\DBAL\Schema\AbstractSchemaManager``

For an already supported platform but unsupported driver you only
need to implement the first three interfaces, since the SQL
Generation and Schema Management is already supported by the
respective platform and schema instances. You can also make use of
several Abstract unit tests in the ``\Doctrine\DBAL\Tests`` package
to check if your platform behaves like all the others which is
necessary for SchemaTool support, namely:

-  ``\Doctrine\DBAL\Tests\Platforms\AbstractPlatformTestCase``
-  ``\Doctrine\DBAL\Tests\Functional\Schema\AbstractSchemaManagerTestCase``

We would be very happy if any support for new databases would be
contributed back to Doctrine to make it an even better product.

Implementation Steps in Detail
------------------------------

1. Add your driver shortcut to the ``Doctrine\DBAL\DriverManager`` class.
2. Make a copy of tests/dbproperties.xml.dev and adjust the values to your driver shortcut and testdatabase.
3. Create three new classes implementing ``\Doctrine\DBAL\Driver\Connection``, ``\Doctrine\DBAL\Driver\Statement``
   and ``Doctrine\DBAL\Driver``. You can take a look at the ``Doctrine\DBAL\Driver\OCI8`` driver.
4. You can run the test suite of your new database driver by calling ``phpunit``. You can set your own settings in the phpunit.xml file.
5. Start implementing AbstractPlatform and AbstractSchemaManager. Other implementations should serve as good examples.
