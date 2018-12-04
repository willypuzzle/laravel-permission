<?php

namespace Idsign\Permission\Exceptions;

use InvalidArgumentException;

class ContainerAlreadyExists extends InvalidArgumentException
{
    public static function create(string $containerName, string $guardName)
    {
        return new static("A container `{$containerName}` already exists for guard `{$guardName}`.");
    }
}
