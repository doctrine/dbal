Upgrading
=========

New versions of Doctrine come with an upgrade guide named UPGRADE.md.
This guide documents BC-breaks and deprecations.

Deprecations
------------

Deprecations are signaled by emitting a silenced ``E_USER_DEPRECATED``
error, like this:

.. code-block:: php

    <?php

    @trigger_error(
        'QuantumDefraculator::__invoke() is deprecated.',
        E_USER_DEPRECATED
    );

Since this error is silenced, it will not produce any effect unless you
opt-in by setting up an error handler designed to ignore the silence
operator in that case. Such an error handler could look like this:

.. code-block:: php

    <?php
    set_error_handler(function (
        int $errno,
        string $errstr,
        string $errfile,
        int $errline,
        array $errcontext
    ) : void {
        if (error_reporting() === 0) {
            /* "normal" error handlers would return in this case, but
               this one will not */
        }

        echo "Hey there was a deprecation, here is what it says: $errstr";

    }, E_USER_DEPRECATED);

This is of course overly simplified, and if you are looking for such an
error handler, consider the ``symfony/debug``, error handler that will
log deprecations. You may also be interested by the
``symfony/phpunit-bridge`` error handler that will catch deprecations
and nicely display them after running your test suites, and can even
make your build fail in that kind of case if you want to be strict about
that.
