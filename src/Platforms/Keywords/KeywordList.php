<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Keywords;

use function array_flip;
use function array_map;
use function strtoupper;

/**
 * Abstract interface for a SQL reserved keyword dictionary.
 */
abstract class KeywordList
{
    /** @var string[]|null */
    private ?array $keywords = null;

    /**
     * Checks if the given word is a keyword of this dialect/vendor platform.
     */
    public function isKeyword(string $word): bool
    {
        if ($this->keywords === null) {
            $this->initializeKeywords();
        }

        return isset($this->keywords[strtoupper($word)]);
    }

    protected function initializeKeywords(): void
    {
        $this->keywords = array_flip(array_map('strtoupper', $this->getKeywords()));
    }

    /**
     * Returns the list of keywords.
     *
     * @return string[]
     */
    abstract protected function getKeywords(): array;
}
