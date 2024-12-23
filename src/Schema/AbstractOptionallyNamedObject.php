<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

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
     * @var ?N
     */
    protected ?Name $name;

    public function __construct(?string $name)
    {
        parent::__construct($name ?? '');
    }

    public function getObjectName(): ?Name
    {
        return $this->name;
    }

    protected function setName(?Name $name): void
    {
        $this->name = $name;
    }
}
