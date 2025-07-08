<?php

namespace Cerberus\Facades;

use Illuminate\Support\Facades\Facade;
/**
 * @method static \Cerberus\CerberusManager manager()
 */
class Cerberus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cerberus';
    }
}
