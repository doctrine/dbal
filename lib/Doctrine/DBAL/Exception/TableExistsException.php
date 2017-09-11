<?php

namespace Doctrine\DBAL\Exception;

/**
 * Exception for an already existing table referenced in a statement detected in the driver.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class TableExistsException extends DatabaseObjectExistsException
{
}
