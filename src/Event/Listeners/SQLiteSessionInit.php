<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event\Listeners;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;

class SQLiteSessionInit implements EventSubscriber
{
    /** @throws Exception */
    public function postConnect(ConnectionEventArgs $args): void
    {
        $args->getConnection()->executeStatement('PRAGMA foreign_keys=ON');
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [Events::postConnect];
    }
}
