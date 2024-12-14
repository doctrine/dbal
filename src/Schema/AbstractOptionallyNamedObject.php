<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\NameIsNotInitialized;

/**
 * An abstract {@see OptionallyNamedObject}.
 *
 * @template N of Name
 * @extends AbstractAsset<N>
 * @implements OptionallyNamedObject<N>
 */
abstract class AbstractOptionallyNamedObject extends AbstractAsset implements OptionallyNamedObject
{
    /**
     * The name of the database object.
     *
     * Until the validity of the name is enforced, this property isn't guaranteed to be always initialized. The property
     * can be accessed only if {@see $isNameInitialized} is set to true.
     *
     * @var ?N
     */
    protected ?Name $name;

    public function __construct(?string $name)
    {
        parent::__construct($name ?? '');
    }

    public function getObjectName(): ?Name
    {
        if (! $this->isNameInitialized) {
            throw NameIsNotInitialized::new();
        }

        return $this->name;
    }

    protected function setName(?Name $name): void
    {
        $this->name = $name;
    }
}
