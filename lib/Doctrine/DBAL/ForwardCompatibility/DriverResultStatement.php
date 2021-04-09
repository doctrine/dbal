<?php

namespace Doctrine\DBAL\ForwardCompatibility;

use Doctrine\DBAL;

interface DriverResultStatement extends DBAL\Driver\ResultStatement, DBAL\Result
{
}
