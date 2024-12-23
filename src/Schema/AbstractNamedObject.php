<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidName;

/**
 * An abstract {@see NamedObject}.
 *
 * @template N of Name
 * @extends AbstractAsset<N>
 * @implements NamedObject<N>
 */
abstract class AbstractNamedObject extends AbstractAsset implements NamedObject
{
    /**
     * The name of the database object.
     *
     * @var N
     */
    protected Name $name;

    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    /**
     * Returns the object name.
     *
     * @return N
     */
    public function getObjectName(): Name
    {
        return $this->name;
    }

    protected function setName(?Name $name): void
    {
        if ($name === null) {
            throw InvalidName::fromEmpty();
        }

        $this->name = $name;
    }
}
