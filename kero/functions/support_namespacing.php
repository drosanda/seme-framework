<?php
/**
* Global functions for support namespace
*
* @author Daeng Rosanda
* @version 4.0.3
*
* @package SemeFramework\Kero\Functions
* @since 4.0.3
*/

/**
 * Procedure for registering namespace into current session
 * This method will allowed SemeFramework core loaded controller or model if namespace is specified
 *
 * @param  string $namespace               [description]
 *
 * @return void
 */
function register_namespace($namespace = __NAMESPACE__)
{
    $_SESSION['namespace'] = $namespace;
}
