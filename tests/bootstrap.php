<?php
/**
 * PHPUnit bootstrap for Seme Framework.
 *
 * Kept deliberately minimal: it only defines the constants and directory
 * registry the core classes expect. Extend it as heavier classes
 * (Controller, Model, Engine) get tests.
 *
 * @codeCoverageIgnore
 */

if (!defined('SEMEROOT')) {
    define('SEMEROOT', dirname(__DIR__).DIRECTORY_SEPARATOR);
}
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('EXT')) {
    define('EXT', '.php');
}
if (!defined('TEM_ERR')) {
    define('TEM_ERR', 'Error');
}

$GLOBALS['SEMEDIR'] = new stdClass();
foreach (array(
    'app'            => 'app',
    'app_config'     => 'app/config',
    'app_controller' => 'app/controller',
    'app_model'      => 'app/model',
    'app_view'       => 'app/view',
    'kero'           => 'kero',
    'kero_lib'       => 'kero/lib',
    'kero_sine'      => 'kero/sine',
) as $alias => $dir) {
    $GLOBALS['SEMEDIR']->$alias = SEMEROOT.$dir.DIRECTORY_SEPARATOR;
}
