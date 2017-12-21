<?php

namespace Doctrine\DBAL\Exception;

/**
 * Exception for a write operation attempt on a read-only database element detected in the driver.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class ReadOnlyException extends ServerException
{
}
