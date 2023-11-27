<?php


namespace Develona\Translate\Facades;

use Illuminate\Support\Facades\Facade;

class T extends Facade
{
   protected static function getFacadeAccessor()
   {
       return 'translate';
   }
}