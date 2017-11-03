<?php

namespace Idsign\Permission\Exceptions;

use InvalidArgumentException;

class SectionDoesNotExist extends InvalidArgumentException
{
    public static function create(string $sectionName, string $guardName)
    {
        return new static("There is no section named `{$sectionName}` for guard `{$guardName}``.");
    }
}
