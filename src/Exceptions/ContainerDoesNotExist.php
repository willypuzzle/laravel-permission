<?php

namespace Idsign\Permission\Exceptions;

use InvalidArgumentException;

class ContainerDoesNotExist extends InvalidArgumentException
{
    public static function create(string $containerName, string $guardName)
    {
        return new static("There is no container named `{$containerName}` for guard `{$guardName}``.");
    }
}
