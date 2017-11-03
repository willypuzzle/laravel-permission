<?php

namespace Idsign\Permission\Exceptions;

use InvalidArgumentException;

class SectionAlreadyExists extends InvalidArgumentException
{
    public static function create(string $sectionName, string $guardName)
    {
        return new static("A `{$sectionName}` permission already exists for guard `{$guardName}`.");
    }
}
