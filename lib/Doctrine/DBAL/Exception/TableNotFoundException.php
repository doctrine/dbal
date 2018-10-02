<?php

namespace Doctrine\DBAL\Exception;

/**
 * Exception for an unknown table referenced in a statement detected in the driver.
 *
 * @link   www.doctrine-project.org
 */
class TableNotFoundException extends DatabaseObjectNotFoundException
{
}
