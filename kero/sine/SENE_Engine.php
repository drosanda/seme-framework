<?php
/** CONSTANTS */
if (!defined('TEM_ERR')) {
  define('TEM_ERR', 'Error');
}
if (!defined('LBL_BASE_URL')) {
  define('LBL_BASE_URL', '{{base_url}}');
}
if (!defined('LBL_BASE_URL_ADMIN')) {
  define('LBL_BASE_URL_ADMIN', '{{base_url_admin}}');
}
if (!defined('LBL_CDN_URL')) {
  define('LBL_CDN_URL', '{{cdn_url}}');
}

/**
 * Main Engine Class of seme framework
 * Contains loader to another Seme Framework Classes
 *
 * @author Daeng Rosanda
 *
 * @version 4.0.7
 *
 * @package SemeFramework\Kero
 * @since 3.0.0
 *
 * @codeCoverageIgnore
 */
#[AllowDynamicProperties]
class SENE_Engine
{
    protected static $__instance;

    public $directories;
    public $config;
    public $notfound;
    public $default;
    public $core_prefix = 'SM_';
    public $core_controller = '';
    public $core_model = '';

    public function __construct()
    {
        $this->directories = $GLOBALS['SEMEDIR'];
        $this->config = $GLOBALS['SEMECFG'];
        $this->default = $this->config->controller_main;
        $this->notfound = $this->config->controller_404;

        require_once $this->directories->kero_sine.'SENE_Input.php';
        require_once $this->directories->kero_sine.'SENE_Controller.php';
        require_once $this->directories->kero_sine.'SENE_Model.php';

        $rs = array();
        foreach ($this->config->routes as $key=>$val) {
            $key = trim($key, '/');
            $val = trim($val, '/');
            $rs[$key] = $val;
        }
        $this->config->routes = $rs;

        $sene_method = $this->config->method;
        if (isset($_SERVER['argv']) && count($_SERVER['argv'])>1) {
            $i=0;
            $_SERVER[$sene_method] = '';
            foreach ($_SERVER['argv'] as $argv) {
                $i++;
                if ($i==1) {
                    continue;
                }
                $_SERVER[$sene_method] .= '/'.$argv;
            }
            unset($i);
            unset($argv);
        }
        unset($sene_method);
        self::$__instance = $this;
        $GLOBALS['SEMECFG'] = $this->config;
    }

    /**
     * Get namespace string from current loaded controller class file
     *
     * @return string namespace from current controller
     */
    private function namespace()
    {
        $namespace = '';
        if (isset($_SESSION['namespace']) && strlen($_SESSION['namespace'])) {
            $namespace = $_SESSION['namespace'];
        }
        return $namespace;
    }

    /**
     * Get singleton instance
     *
     * @return object Current Seme Framework Engine object
     */
    public static function getInstance()
    {
        return self::$__instance;
    }

    /**
     * Run the framework
     *
     * @return void
     */
    public function run()
    {
        if (strlen($this->config->core_prefix)) {
            if (strlen($this->config->core_controller)) {
                $core_controller_file = $this->directories->app_core.$this->config->core_prefix.$this->config->core_controller.'.php';
                if (file_exists($core_controller_file)) {
                    require_once($core_controller_file);
                } else {
                    $error_msg = 'unable to load core controller on '.$core_controller_file;
                    error_log($error_msg);
                    trigger_error($error_msg);
                }
            }
            if (strlen($this->config->core_model)) {
                $core_model_file = $this->directories->app_core.$this->config->core_prefix.$this->config->core_model.'.php';
                if (file_exists($core_model_file)) {
                    require_once($core_model_file);
                } else {
                    $error_msg = 'unable to load core model on '.$core_model_file;
                    error_log($error_msg);
                    trigger_error($error_msg);
                }
            }
        }
        $this->newRouteFolder();
    }

    private function defaultController()
    {
        $cname = $this->default.'';
        require_once $this->directories->app_controller.$this->default.".php";
        if (!class_exists($cname, false)) {
            $cname = $this->namespace().'\\'.$cname;
        }
        $cname = new $cname();
        $cname->index();
    }

    /**
     * Check that a resolved (realpath) location is inside the controller directory
     *
     * @param string|false $realpath Result of realpath()
     * @return bool true if path exists and lives under app_controller
     */
    private function insideControllerDir($realpath)
    {
        if ($realpath === false) {
            return false;
        }
        $base = realpath($this->directories->app_controller);
        if ($base === false) {
            return false;
        }
        $base = rtrim($base, '/\\');
        return $realpath === $base || strpos($realpath, $base.DIRECTORY_SEPARATOR) === 0;
    }

    /**
     * Check that a method may be reached from a URL
     *
     * @param string $cname Controller class name
     * @param string $func  Method name
     * @return bool
     */
    private function isRoutableMethod($cname, $func)
    {
        if (!method_exists($cname, $func) || strpos($func, '__') === 0) {
            return false;
        }
        $reflection = new ReflectionMethod($cname, $func);
        if (!$reflection->isPublic()) {
            return false;
        }
        return $reflection->getDeclaringClass()->getName() !== 'SENE_Controller';
    }

    /**
     * Validate the method, instantiate the controller and invoke the method
     * with arguments taken from the URL segments starting at $offset.
     *
     * @param string $cname    Controller class name
     * @param string $func     Method name from URL
     * @param array  $path     URL segments
     * @param int    $offset   Index of first argument segment
     * @param string $newpath  Directory used to look for a custom 404 controller
     * @return void
     */
    private function callController($cname, $func, $path, $offset, $newpath='')
    {
        $func = strtr($func, '-', '_');
        if (!$this->isRoutableMethod($cname, $func)) {
            $this->notFound($newpath);
            return;
        }
        $reflection = new ReflectionMethod($cname, $func);
        $controller = new $cname();
        $args = array();
        foreach ($reflection->getParameters() as $j => $param) {
            if (isset($path[$offset+$j])) {
                $args[] = $path[$offset+$j];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }
        $reflection->invokeArgs($controller, $args);
    }

    private function notFound($newpath='')
    {
        $cname = $this->notfound.'';
        $dir = '';
        if (is_string($newpath) && $newpath !== '') {
            $dir = rtrim($newpath, '/').'/';
        }
        if ($dir !== '' && file_exists($dir.$this->notfound.".php")) {
            require_once($dir.$this->notfound.".php");
        } else {
            require_once($this->directories->app_controller.$this->notfound.".php");
        }
        if (!class_exists($cname, false)) {
            $cname = $this->namespace().'\\'.$cname;
        }
        $cname = new $cname();
        $cname->index();
    }

    private function globalHandlerCMSPage($path=array())
    {
        if (count($path)>1) {
            $slug_parent = $path[1];
            $slug_child = '';
            if (isset($path[2])) {
                $slug_child = $path[2];
            }
            $filename = realpath($this->directories->app_controller."cms_handler.php");
            if (is_file($filename) && !empty($slug_parent)) {
                require_once $filename;
                $cname = basename($filename, ".php");
                $cname = strtr($cname,'-', '_');
                if (class_exists($cname)) {
                    $cname = new $cname();
                    $func = "slugParent";
                    $reflection = new ReflectionMethod($cname, $func);
                    $args = array();
                    $args[0] = $slug_parent;
                    $args[1] = $slug_child;
                    $reflection->invokeArgs($cname, $args);
                }
            }
        }
    }

    private function ovrRoutes($paths=array())
    {
        $path = strtolower(implode('/', $paths));
        $path = trim($path, '/');
        $target = '';
        $routes = $this->config->routes;
        foreach ($routes as $key=>$val) {
            $key = strtolower(trim($key, '/'));
            $val = strtolower(trim($val, '/'));
            $key = str_replace(array(':any', ':num'), array('[^/]+', '[0-9]+'), $key);
            if (preg_match('#^'.$key.'$#', $path, $matches)) {
                $target = '/'.preg_replace('#^'.$key.'$#', $val, $path);
            }
        }
        if (!empty($target)) {
            return explode('/', $target);
        }
        return $paths;
    }

    private function newRouteFolder()
    {
        $sene_method = $this->config->method;
        if (isset($_SERVER[$sene_method])) {
            $path = $_SERVER[$sene_method];
            $path = strtr($path,'//', '/');
            $path = explode('/', strtr($path,'//', '/'));
            $i=0;
            foreach ($path as $p) {
                if (strlen($p)>0) {
                    $pos = strpos($p, '?');
                    if ($pos !== false) {
                        unset($path[$i]);
                    }
                }
                $i++;
            }
            unset($p);
            $path = $this->ovrRoutes($path);
            foreach ($path as $segment) {
                if ($segment === '.' || $segment === '..') {
                    $this->notFound();
                    return;
                }
            }
            unset($segment);
            if (!isset($path[1])) {
                $path[1] = '';
            }
            $path[1] = strtr($path[1],'-', '_');
            if (!empty($path[1])) {
                if ($path[1] == "admin" && $this->config->baseurl_admin !="admin") {
                    $this->notFound();
                    return;
                }

                if ($this->config->baseurl_admin==$path[1]) {
                    $path[1]="admin";
                } else {
                    $this->globalHandlerCMSPage($path);
                }

                $newpath = realpath($this->directories->app_controller.$path[1]);
                if ($newpath !== false && !$this->insideControllerDir($newpath)) {
                    $this->notFound();
                    return;
                }

                if ($newpath !== false && is_dir($newpath)) {
                    $newpath = rtrim($newpath, '/').'/';
                    if (empty($path[2])) {
                        $path[2] = 'home';
                    }
                    $dirn = realpath($newpath.$path[2]);
                    $filename = realpath($newpath.$path[2].".php");

                    if ($dirn !== false && is_dir($dirn) && $this->insideControllerDir($dirn)) {
                        $dirn = rtrim($dirn, '/').'/';
                        if (empty($path[3])) {
                            $path[3] = 'home';
                        }
                        $filename = realpath($dirn.$path[3].".php");
                        if ($filename !== false && is_file($filename) && $this->insideControllerDir($filename)) {
                            require_once $filename;
                            $cname = strtr(basename($filename, ".php"), '-', '_');

                            if (!class_exists($cname, false)) {
                                $cname = $this->namespace().'\\'.$cname;
                            }

                            if (!class_exists($cname, false)) {
                                seme_fatal("Unable to load class: $cname. Please check classname on controller is exists in ".$this->directories->app_controller.$path[2].'/'.$path[3].".php");
                                return;
                            }

                            $func = "index";
                            if (!empty($path[4])) {
                                $func = $path[4];
                            }
                            $this->callController($cname, $func, $path, 5, $newpath);
                        } else {
                            $this->notFound($newpath);
                        }
                    } elseif ($filename !== false && is_file($filename) && $this->insideControllerDir($filename)) {
                        require_once $filename;
                        $cname = strtr(basename($filename, ".php"), '-', '_');

                        if (!class_exists($cname, false)) {
                            $cname = $this->namespace().'\\'.$cname;
                        }

                        if (!class_exists($cname, false)) {
                            seme_fatal("Unable to load class: $cname. Please check classname on controller is exists in ".$this->directories->app_controller." triggered ");
                            return;
                        }
                        $func = "index";
                        if (!empty($path[3])) {
                            $func = $path[3];
                        }
                        $this->callController($cname, $func, $path, 4, $newpath);
                    } else {
                        $this->notFound($newpath);
                    }
                } else {
                    $filename = realpath($this->directories->app_controller.$path[1].".php");
                    if ($filename !== false && is_file($filename) && $this->insideControllerDir($filename)) {
                        require_once $filename;
                        $cname = strtr(basename($filename, ".php"), '-', '_');

                        if (!class_exists($cname, false)) {
                            $cname = $this->namespace().'\\'.$cname;
                        }

                        if (class_exists($cname, false)) {
                            $func = "index";
                            if (!empty($path[2])) {
                                $func = $path[2];
                            }
                            $this->callController($cname, $func, $path, 3);
                        } else {
                            $this->notFound();
                        }
                    } else {
                        $this->notFound();
                    }
                }
            } else {
                $this->defaultController();
            }
        } else {
            $this->defaultController();
        }
    }
}
