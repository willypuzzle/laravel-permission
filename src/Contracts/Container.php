<?php

namespace Idsign\Permission\Contracts;

interface Container
{
    const ENABLED = 1;
    const DISABLED = 0;
    const ALL_STATES  = [ self::DISABLED, self::ENABLED ];
}
