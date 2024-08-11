<?php
/**
* Global debugger functions that use by accross application
*
* @author Daeng Rosanda
* @version 4.0.3
*
* @package SemeFramework\Kero\Functions
* @since 4.0.3
*/

/**
* Var Dumper
* @param  mixed  $var  any variable that can be dumped
*/
function dd($var)
{
    echo "<pre>";
    print_r($var);
    exit;
}