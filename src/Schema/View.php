<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Override;

/**
 * Representation of a Database View.
 *
 * @implements NamedObject<OptionallyQualifiedName>
 */
final readonly class View implements NamedObject
{
    /** @internal Use {@link View::editor()} to instantiate an editor and {@link ViewEditor::create()} to create a view. */
    public function __construct(private OptionallyQualifiedName $name, private string $sql)
    {
    }

    #[Override]
    public function getObjectName(): OptionallyQualifiedName
    {
        return $this->name;
    }

    public function getSQL(): string
    {
        return $this->sql;
    }

    /**
     * Instantiates a new view editor.
     */
    public static function editor(): ViewEditor
    {
        return new ViewEditor();
    }

    /**
     * Instantiates a new view editor and initializes it with the view's properties.
     */
    public function edit(): ViewEditor
    {
        return self::editor()
            ->setName($this->getObjectName())
            ->setSQL($this->sql);
    }
}
