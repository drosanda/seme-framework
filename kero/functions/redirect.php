<?php
/**
* Global redir functions that use by accross application
*
* @author Daeng Rosanda
* @version 4.0.3
*
* @package SemeFramework\Kero\Functions
* @since 4.0.3
*/

/**
* Redirect to target url

* @param  string  $url  target full qualified url
* @param  integer $time delay time
* @param  integer $type type of redirection, 1> html, 0 http header
*/
function redir($url, $time=0, $type=0)
{
    if ($type=="1" || $type==1) {
        if ($time) {
            echo '<meta http-equiv="refresh" content="'.$time.';URL=\''.$url.'\'" />';
        } else {
            echo '<meta http-equiv="refresh" content="1;URL=\''.$url.'\'" />';
        }
    } else {
        if ($time>1) {
            header('HTTP/1.1 301 Moved Permanently');
            header("Refresh:".$time."; url=".$url);
        } else {
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $url);
        }
    }
}