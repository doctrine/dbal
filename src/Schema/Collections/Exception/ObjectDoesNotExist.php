<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections\Exception;

use Doctrine\DBAL\Schema\Collections\Exception;
use Doctrine\DBAL\Schema\Name;
use LogicException;

use function sprintf;

/** @internal */
final class ObjectDoesNotExist extends LogicException implements Exception
{
    public function __construct(string $message, private readonly Name $objectName)
    {
        parent::__construct($message);
    }

    public function getObjectName(): Name
    {
        return $this->objectName;
    }

    public static function new(Name $objectName): self
    {
        return new self(sprintf('Object %s does not exist.', $objectName->toString()), $objectName);
    }
}
