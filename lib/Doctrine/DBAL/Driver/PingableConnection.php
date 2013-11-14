<?php
namespace Doctrine\DBAL\Driver;

interface PingableConnection
{
    public function ping();
}