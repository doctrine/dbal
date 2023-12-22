<?php

namespace Doctrine\DBAL;

// TODO: convert to Enum
interface LimitOffsetPlaceholders
{
    public const LIMIT_PLACEHOLDER = '__doctrine_limit_placeholder__';
    public const OFFSET_PLACEHOLDER = '__doctrine_offset_placeholder__';
}