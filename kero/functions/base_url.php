<?php
/**
* Global base_url functions that use by accross application
*
* @author Daeng Rosanda
* @version 4.0.3
*
* @package SemeFramework\Kero\Functions
* @since 4.0.3
*/

/**
* Get relatives base url
* @param  string $url addtional url
* @return string      full base url
*/
function base_url($url='')
{
    return $GLOBALS['SEMECFG']->baseurl.$url;
}
/**
* Set admin secret base url
* @param  string $url addtional url
* @return string      full url
*/
function base_url_admin($url='')
{
    return  $GLOBALS['SEMECFG']->baseurl.$GLOBALS['SEMECFG']->baseurl_admin.'/'.$url;
}

/**
* Set front scoped base url
*
* @param  string $url addtional url
*
* @return string      full url
*/
function base_url_front($url='')
{
    return  $GLOBALS['SEMECFG']->baseurl.$GLOBALS['SEMECFG']->baseurl_front.'/'.$url;
}