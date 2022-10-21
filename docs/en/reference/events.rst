Events
======

Both ``Doctrine\DBAL\DriverManager`` and
``Doctrine\DBAL\Connection`` accept an instance of
``Doctrine\Common\EventManager``. The EventManager has a couple of
events inside the DBAL layer that are triggered for the user to
listen to.

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
