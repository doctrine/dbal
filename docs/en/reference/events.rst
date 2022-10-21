Events
======

Both ``Doctrine\DBAL\DriverManager`` and
``Doctrine\DBAL\Connection`` accept an instance of
``Doctrine\Common\EventManager``. The EventManager has a couple of
events inside the DBAL layer that are triggered for the user to
listen to.

PostConnect Event
-----------------

``Doctrine\DBAL\Events::postConnect`` is triggered right after the
connection to the database is established. It allows to specify any
relevant connection specific options and gives access to the
``Doctrine\DBAL\Connection`` instance that is responsible for the
connection management via an instance of
``Doctrine\DBAL\Event\ConnectionEventArgs`` event arguments
instance.

Doctrine ships with one implementation for the "PostConnect" event:

-  ``Doctrine\DBAL\Event\Listeners\OracleSessionInit`` allows to
   specify any number of Oracle Session related environment variables
   that are set right after the connection is established.

You can register events by subscribing them to the ``EventManager``
instance passed to the Connection factory:

.. code-block:: php

    <?php
    $evm = new EventManager();
    $evm->addEventSubscriber(new OracleSessionInit([
        'NLS_TIME_FORMAT' => 'HH24:MI:SS',
    ]));

    $conn = DriverManager::getConnection($connectionParams, null, $evm);

Schema Events
-------------

There are multiple events in Doctrine DBAL that are triggered on schema changes
of the database. It is possible to add your own event listener to be able to run
your own code before changes to the database are committed. An instance of
``Doctrine\Common\EventManager`` can also be added to :doc:`platforms`.

A event listener class can contain one or more methods to schema events. These
methods must be named like the events itself.

.. code-block:: php

    <?php
    $evm = new EventManager();
    $eventName = Events::onSomething;
    $evm->addEventListener($eventName, new MyEventListener());

.. code-block:: php

    <?php
    $evm = new EventManager();
    $eventNames = [Events::onSomething, Events::onSomethingElse];
    $evm->addEventListener($eventNames, new MyEventListener());
