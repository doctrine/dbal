<?php

namespace Doctrine\DBAL\Exception;

/**
 * Exception for a unique constraint violation detected in the driver.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class UniqueConstraintViolationException extends ConstraintViolationException
{
}
