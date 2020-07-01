<?php
/**
 * Abstract class for controller wrapper
 * @var [type]
 */
abstract class SENE_Controller
{
    protected static $__instance;
    public $input;
    public $db;
    public $lang = 'en';
    public $title = 'SEME Framework';
    public $content_language = 'id';
    public $canonical = 'id';
    public $pretitle = 'SEME Framework';
    public $posttitle = 'SEME Framework';
    public $robots = 'INDEX,FOLLOW';
    public $description = 'Created By Seme Framework. The light weight framework that fit your needs with automation generated model.';
    public $keyword = 'lightweight, framework, php, api, generator';
    public $author = 'SEME Framework';
    public $icon = 'favicon.png';
    public $shortcut_icon = 'favicon.png';
    public $content_type = 'text/html; charset=utf-8';
    public $additional = array();
    public $additionalBefore = array();
    public $additionalAfter = array();
    public $theme = 'front/';
    public $js_footer = array();
    public $js_ready = "";
    
    //put unrendered content for view
    public $__content = ""; 
    
    //used by putThemeContent
    public $__themeContent = ""; 
    
    //used by putRightThemeContent
    public $__themeRightContent = "";
    
    //use by putLeftThemeContent
    public $__themeLeftContent = ""; 
    
    //use by putLeftThemeContent
    public $__themeRightMenu = "";
    
    public $__bodyBefore = "";
    
    
    public function __construct()
    {
        $this->directories = $GLOBALS['SEMEDIR'];
        $this->config = $GLOBALS['SEMECFG'];
        $this->input = new SENE_Input();
        $this->additional = $this->getThemeConfig();
        $this->js_footer = $this->getJsFooterBasic();
        $this->__content = '';
        $this->__themeContent = '';
        $this->__themeRightContent = '';
        $this->__themeLeftContent = '';
        $this->__jsContent = '';
        self::$__instance = $this;
    }
    
    /**
     * Loads CSS and another header files from theme.json
     * relatives to theme location
     */
    private function getThemeConfig()
    {
        if (file_exists($this->directories->app_view.'/'.$this->getTheme().'/theme.json')) {
            return json_decode($this->fgc($this->directories->app_view.$this->getTheme().'/theme.json'));
        } else {
            return array();
        }
    }
    
    /**
     * Loads javascript from script.json
     * relative to theme location
     * @return [type] [description]
     */
    private function getJsFooterBasic()
    {
        if (file_exists($this->directories->app_view.'/'.$this->getTheme().'/script.json')) {
            return json_decode($this->fgc($this->directories->app_view.$this->getTheme().'/script.json'));
        } else {
            return array();
        }
    }
    
    /**
     * Set theme location, relative to app/view
     * @param string $theme name of directory theme, e.g. front
     */
    public function setTheme(string $theme="front")
    {
        $theme = rtrim($theme, '/').'/';
        $this->theme = $theme;
        $this->additional = $this->getThemeConfig();
        $this->js_footer = $this->getJsFooterBasic();
        return $this;
    }
    
    /**
     * Load the model or library from controller and instantiate object with same name in controller
     * if model relatives to app/model
     * if library relatives to kero/lib
     * @param  string $a          location and name of view without .php suffix
     * @param  string $b          alias of instantiate object, default empty
     * @param  string $c       [description]
     * @return object             return this class
     */
    protected function load(string $a, string $b="", string $c="model")
    {
        if ($c=="model") {
            $mfile = $this->directories->app_model.$a.'.php';
            $cname = basename($mfile, '.php');
            if (empty($b)) {
                $b = $cname;
            }
            $b = strtolower($b);
            if (file_exists($mfile)) {
                if (!class_exists($cname)) {
                    require_once $mfile;
                }
                $this->{$b} = new $cname();
            } else {
                trigger_error('could not find model '.$a.'  on '.$mfile);
                //die();
            }
        } elseif ($c=="lib") {
            $mfile = $this->directories->kero_lib.$a.'.php';
            if (empty($b)) {
                $b = $cname;
            }
            $b = strtolower($b);
            if (file_exists($mfile)) {
                require_once $mfile;
                $this->$b = new $b();
            } else {
                trigger_error('could not find library '.$a.'  on '.$mfile);
            }
        } else {
            if (file_exists($this->directories->kero_lib.$a.'.php')) {
                require_once $this->directories->kero_lib.$a.'.php';
            } else {
                trigger_error('could not find require_once library '.$a.' on '.$mfile);
            }
        }
        return $this;
    }
    
    /**
     * bring view from another file
     *   relatives from theme location
     * @param  string $a         location and name of view without .php suffix
     * @param  array  $__forward data that will be passed to 
     * @return object            return this class
     */
    public function getThemeElement(string $a="", string $__forward=array(), int $cacheable=0)
    {
        if (!empty($a)) {
            $this->view(str_replace("//", "/", $this->theme.DS.$a), $__forward);
            $this->render($cacheable);
        }
        return $this;
    }
    
    /**
     * For loading layout from a theme
     * Default file location app/view/front/page/
     * @param  string $u          name of layout without .php suffix
     * @param  array  $__forward  data that will be passed to 
     * @return object             return this class
     */
    public function loadLayout($a, $__forward=array())
    {
        if (empty($a)) {
            trigger_error("Layout not found. Please check layout file at ".$this->directories->app_view.$this->getTheme()."page/ executed", E_USER_ERROR);
        }
        $this->view($this->getTheme()."page/".$a, $__forward);
        return $this;
    }
    
    /**
     * Empty the __themeContent variable
     * @return object            return this class
     */
    public function resetThemeContent()
    {
        $this->__themeContent = '';
        return $this;
    }
    
    /**
     * Inject view to a layout 
     * @param  string $u         relative theme location filename without .php extension
     * @param  array  $__forward  data that will be passed to 
     * @return object             return this class
     */
    public function putThemeContent($u="", $__forward=array())
    {
        $v = $this->directories->app_view.$this->theme.'/'.$u;
        if (file_exists($v.".php")) {
            $keytemp=md5(date("h:i:s"));
            $_SESSION[$keytemp] = $__forward;
            extract($_SESSION[$keytemp]);
            unset($_SESSION[$keytemp]);
            ob_start();
            require_once($v.".php");
            $this->__themeContent .= ob_get_contents();
            ob_end_clean();
            return 0;
        } else {
            $v = str_replace('\\', '/', $v);
            $v = str_replace('//', '/', $v);
            $v = str_replace(SEMEROOT, '', $v);
            trigger_error("unable to putThemeContent ".$v.".php");
            die();
        }
        return $this;
    }
    public function getRightMenuTitle()
    {
        return $this->__themeRightMenu;
    }
    public function setRightMenuTitle($title="")
    {
        $this->__themeRightMenu = $title;
    }
    
    /**
     * Inject view for left content 
     * @param  string $a          [description]
     * @param  array  $b          [description]
     * @return object             this class
     */
    public function putThemeRightContent($a="", $b=array())
    {
        $v = $this->directories->app_view.$this->theme.'/'.$a;
        //die($v);
        if (file_exists($v.".php")) {
            $keytemp=md5(date("h:i:s"));
            $_SESSION[$keytemp] = $b;
            //print_r($_SESSION);
            extract($_SESSION[$keytemp]);
            unset($_SESSION[$keytemp]);
            ob_start();
            require_once($v.".php");
            $this->__themeRightContent = ob_get_contents();
            ob_end_clean();
            return 0;
        } else {
            $v = str_replace('\\', '/', $v);
            $v = str_replace('//', '/', $v);
            $v = str_replace(SEMEROOT, '', $v);
            trigger_error("unable to putThemeRightContent ".$v.".php");
            die();
        }
    }
    
    /**
     * Inject view for left content 
     * @param  string $a          [description]
     * @param  array  $b          [description]
     * @return object             this class
     */
    public function putThemeLeftContent($a="", $b=array())
    {
        $v = $this->directories->app_view.$this->theme.'/'.$a;
        //die($v);
        if (file_exists($v.".php")) {
            $keytemp=md5(date("h:i:s"));
            $_SESSION[$keytemp] = $b;
            //print_r($_SESSION);
            extract($_SESSION[$keytemp]);
            unset($_SESSION[$keytemp]);
            ob_start();
            require_once($v.".php");
            $this->__themeLeftContent .= ob_get_contents();
            ob_end_clean();
            return 0;
        } else {
            $v = str_replace('\\', '/', $v);
            $v = str_replace('//', '/', $v);
            $v = str_replace(SEMEROOT, '', $v);
            trigger_error("unable to putThemeLeftContent ".$v.".php");
            die();
        }
    }
    
    /**
     * Inject javascript from php files
     * @param  string $a          [description]
     * @param  array  $b          [description]
     * @return object             this class
     */
    public function putJsReady($a="", $b=array())
    {
        $v = $this->directories->app_view.$this->theme.'/'.$a;
        if (file_exists($v.".php")) {
            $keytemp=md5(date("h:i:s"));
            $_SESSION[$keytemp] = $b;
            //print_r($_SESSION);
            extract($_SESSION[$keytemp]);
            unset($_SESSION[$keytemp]);
            ob_start();
            require_once($v.".php");
            $this->js_ready = ob_get_contents();
            ob_end_clean();
            return 0;
        } else {
            $v = str_replace('\\', '/', $v);
            $v = str_replace('//', '/', $v);
            $v = str_replace(SEMEROOT, '', $v);
            trigger_error("putJsReady unable to load  ".$v.".php");
            die();
        }
    }
    
    /**
     * Get injected main view into a layout
     * @return [type] [description]
     */
    public function getThemeContent()
    {
        echo $this->__themeContent;
    }
    
    /**
     * Get injected right view into a layout
     * @return [type] [description]
     */
    public function getThemeRightContent()
    {
        echo $this->__themeRightContent;
    }
    
    /**
     * Get injected left view into a layout
     * @return [type] [description]
     */
    public function getThemeLeftContent()
    {
        echo $this->__themeLeftContent;
    }
    
    /**
     * Get injected javascript content in php file into a layout
     */
    public function getJsReady()
    {
        echo $this->js_ready;
    }
    
    public function putJsContent($tc="", $__forward=array())
    {
        $v = $this->directories->app_view.$this->theme.'/'.$tc;
        //die($v);
        if (file_exists($v.".php")) {
            $keytemp=md5(date("h:i:s"));
            $_SESSION[$keytemp] = $__forward;
            //print_r($_SESSION);
            extract($_SESSION[$keytemp]);
            unset($_SESSION[$keytemp]);
            ob_start();
            require_once($v.".php");
            $this->__jsContent .= ob_get_contents();
            ob_end_clean();
            return 0;
        } else {
            $v = str_replace('\\', '/', $v);
            $v = str_replace('//', '/', $v);
            $v = str_replace(SEMEROOT, '', $v);
            trigger_error("putJsContent unable to load  ".$v.".php");
            die();
        }
    }
    public function putBodyBefore($tc="", $__forward=array())
    {
        $v = $this->directories->app_view.$this->theme.'/'.$tc;
        //die($v);
        if (file_exists($v.".php")) {
            $keytemp=md5(date("h:i:s"));
            $_SESSION[$keytemp] = $__forward;
            //print_r($_SESSION);
            extract($_SESSION[$keytemp]);
            unset($_SESSION[$keytemp]);
            ob_start();
            require_once($v.".php");
            $this->__bodyBefore .= ob_get_contents();
            ob_end_clean();
            return 0;
        } else {
            $v = str_replace('\\', '/', $v);
            $v = str_replace('//', '/', $v);
            $v = str_replace(SEMEROOT, '', $v);
            trigger_error("putBodyBefore unable to load ".$v.".php");
            die();
        }
    }
    public function getBodyBefore()
    {
        echo $this->__bodyBefore;
    }
    public function getJsContent()
    {
        echo $this->__jsContent;
    }
    
    public function fgc($path)
    {
        $x = json_encode(array());
        if (file_exists($path)) {
            $f = fopen($path, "r");
            if (filesize($path)>0) {
                $x = fread($f, filesize($path));
            }
            fclose($f);
            unset($f);
        }
        return $x;
    }
    public function putJsFooter($stype, $is_external=0)
    {
        if ($is_external) {
            $this->js_footer[] = '<script src="'.$stype.'"></script>';
        } else {
            $stype = rtrim($stype, '.js');
            $this->js_footer[] = '<script src="'.$stype.'.js"></script>';
        }
    }
    public function setCanonical($l="")
    {
        $this->canonical = $l;
    }
    public function getCanonical()
    {
        return $this->canonical;
    }
    public function setContentLanguage($l="en")
    {
        $this->content_language = $l;
    }
    public function getContentLanguage()
    {
        return $this->content_language;
    }
    public function setLang($lang="en")
    {
        $this->lang = $lang;
    }
    public function setTitle($title="SEME FRAMEWORK")
    {
        $this->title = $title;
    }
    public function setDescription($description="en")
    {
        $this->description = $description;
    }
    public function setKeyword($keyword="lightweight,framework,php,api,generator")
    {
        $this->keyword = $keyword;
    }
    public function setRobots($robots="INDEX,FOLLOW")
    {
        if ($robots != "INDEX,FOLLOW") {
            $robots='NOINDEX,NOFOLLOW';
        }
        $this->robots = $robots;
    }
    public function setIcon($icon="favicon.png")
    {
        $this->icon = $icon;
    }
    public function setAuthor($author="SEME Framework")
    {
        $this->author = $author;
    }
    public function setShortcutIcon($shortcut_icon="favicon.png")
    {
        $this->shortcut_icon = $shortcut_icon;
    }
    public function setAdditional($val)
    {
        end($this->additional); // move the internal pointer to the end of the array
        $key = (int)key($this->additional);
        $key = $key+1;
        $this->additional[$key] = $val;
    }
    public function setAdditionalBefore($val)
    {
        if (!is_array($this->additionalBefore)) {
            $this->additionalBefore = array();
        }
        if (is_array($val)) {
            foreach ($val as $v) {
                $this->additionalBefore[] = $v;
                $i++;
            }
        } elseif (is_string($val)) {
            $this->additionalBefore[] = $val;
        }
    }
    public function setAdditionalAfter($val)
    {
        if (!is_array($this->additionalAfter)) {
            $this->additionalAfter = array();
        }
        if (is_array($val)) {
            foreach ($val as $v) {
                $this->additionalAfter[] = $v;
                $i++;
            }
        } elseif (is_string($val)) {
            $this->additionalAfter[] = $val;
        }
    }
    public function loadCss($css_url, $utype="after")
    {
        if (strtolower($utype)=="after") {
            $this->setAdditionalAfter('<link rel="stylesheet" href="'.$css_url.'.css" />');
        } else {
            $this->setAdditionalBefore('<link rel="stylesheet" href="'.$css_url.'.css" />');
        }
    }
    public function putCssAfter($css_url, $utype="after")
    {
        $this->setAdditionalAfter('<link rel="stylesheet" href="'.$css_url.'.css" />');
    }
    public function putCssBefore($css_url)
    {
        $this->setAdditionalBefore('<link rel="stylesheet" href="'.$css_url.'.css" />');
    }
    public function redirToHttps()
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            if ($_SERVER['HTTP_HOST']!="localhost") {
                if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
                    $b = ltrim(base_url(), 'http://');
                    $b = ltrim($b, 's://');
                    $b = rtrim($b, '/');
                    if ($_SERVER['HTTP_X_FORWARDED_PROTO']!="https") {
                        redir(base_url().ltrim($_SERVER['REQUEST_URI'], "/"));
                    //die();
                    } elseif ($_SERVER['HTTP_HOST']!=$b) {
                        redir(base_url().ltrim($_SERVER['REQUEST_URI'], "/"));
                        //die();
                    }
                }
            }
        }
    }

    public function removeAdditional($key)
    {
        unset($this->additional[$key]);
    }
    
    /**
     * Return author name for html head meta language
     * @return string lang
     */
    public function getLang()
    {
        return $this->lang;
    }
    
    /**
     * Return title text for html head title
     * @return string title
     */
    public function getTitle()
    {
        return $this->title;
    }
    
    /**
     * Return author name for html head meta author
     * @return string author
     */
    public function getAuthor()
    {
        return $this->author;
    }
    
    /**
     * Return author name for html head meta description
     * @return string description
     */
    public function getDescription()
    {
        return $this->description;
    }
    
    public function getKeyword()
    {
        return $this->keyword;
    }
    public function getRobots()
    {
        return $this->robots;
    }
    public function getIcon($icon="favicon.png")
    {
        return $this->icon;
    }
    public function getShortcutIcon($shortcut_icon="favicon.png")
    {
        return $this->shortcut_icon;
    }
    public function getAdditionalBefore()
    {
        foreach ($this->additionalBefore as $key=>$a) {
            if (is_string($a)) {
                $a = str_replace("{{base_url}}", base_url(), $a);
                $a = str_replace("{{base_url_admin}}", base_url_admin(), $a);
                echo "\n\t".$a;
            }
        }
    }
    public function getAdditional()
    {
        if (count($this->additional)):
            foreach ($this->additional as $key=>$a) {
                if (is_string($a)) {
                    $a = str_replace("{{base_url}}", base_url(), $a);
                    $a = str_replace("{{base_url_admin}}", base_url_admin(), $a);
                    echo "\n\t".$a;
                }
            }
        endif;
    }
    public function getAdditionalAfter()
    {
        foreach ($this->additionalAfter as $key=>$a) {
            if (is_string($a)) {
                $a = str_replace("{{base_url}}", base_url(), $a);
                $a = str_replace("{{base_url_admin}}", base_url_admin(), $a);
                echo "\n\t".$a;
            }
        }
    }
    
    public function getJsFooter()
    {
        foreach ($this->js_footer as $key=>$a) {
            if (is_string($a)) {
                $a = str_replace("{{base_url}}", base_url(), $a);
                $a = str_replace("{{base_url_admin}}", base_url_admin(), $a);
                echo "\n\t".$a;
            }
        }
    }
    
    public function getContentType()
    {
        return $this->content_type;
    }
    
    /**
     * Get current theme
     * @return string     name of theme
     */
    public function getTheme()
    {
        return $this->theme;
    }
    
    public function getThemeView($el="", $comp='page', $__forward=array(), $cacheable=0)
    {
        if (!empty($el)) {
            $this->view($this->theme.'/'.$comp.'/'.$el, $__forward);
        }
    }
    
    public function isLoggedIn($t="user")
    {
        $sess = $this->getKey();
        if (!is_object($sess)) {
            $sess = new stdClass();
        }
        return isset($sess->$t->id);
    }
    
    /**
     * echo string as HTML5 Entity
     * @param  string $a    string
     */
    public function __($a)
    {
        echo htmlentities((string) $a, ENT_HTML5, 'UTF-8');
    }
    
    public function getLoggedIn($t="user")
    {
        $sess = $this->getKey();
        if (!is_object($sess)) {
            $sess = new stdClass();
        }
        if (isset($sess->$t->id)) {
            return $sess->$t;
        } else {
            return new stdClass();
        }
    }
    public static function getInstance()
    {
        return self::$_instance;
    }

    protected function wrapper($data)
    {
        return array("result"=>$data);
    }
    protected function data_wrapper($data)
    {
        return array("data"=>$data);
    }
    protected function xml_out($data)
    {
        $xml_engine = new XML_Engine($data);
        $xml_engine->parse();
    }
    protected function json_out($data)
    {
        $json_engine = new JSON_Engine($data);
        $json_engine->parse();
    }
    
    protected function view($v, $__forward=array())
    {
        if (file_exists($this->directories->app_view.$v.".php")) {
            $keytemp=md5(date("h:i:s"));
            $_SESSION[$keytemp] = $__forward;
            //print_r($_SESSION);
            extract($_SESSION[$keytemp]);
            unset($_SESSION[$keytemp]);
            ob_start();
            require_once($this->directories->app_view.$v.".php");
            $this->__content = ob_get_contents();
            ob_end_clean();
        } else {
            trigger_error("unable to load view ".$this->directories->app_view.$v.".php ", E_USER_ERROR);
            die("unable to load view ".$this->directories->app_view.$v.".php");
        }
    }
    
    /**
     * Load the library from controller and instantiate object with same name in controller
     * relatives to kero/lib
     * @param  string $a          location and name of view without .php suffix
     * @param  string $b          alias of instantiate object, default empty
     * @param  string $c          type of embedding. lib: autoinstantiate, otherwise include only
     * @return object             return this class
     */
    protected function lib($a, $b="", $c="lib")
    {
        if ($c=='lib') {
            $lpath = str_replace("\\", "/", $this->directories->kero_lib.$a.".php");
            if (file_exists(strtolower($lpath))) {
                require_once(strtolower($lpath));
                $cname = basename($lpath, '.php');
                $method = new $cname();
                if (empty($b)) {
                    $b = $cname;
                }
                $b = strtolower($b);
                $this->{$b} = $method;
            } elseif (file_exists($lpath)) {
                require_once($lpath);
                $cname = basename($lpath, '.php');
                $method = new $cname();
                if (empty($b)) {
                    $b = $cname;
                }
                $b = strtolower($b);
                $this->{$b} = $method;
            } else {
                die("unable to load library on ".$lpath);
            }
        } else {
            if (file_exists(strtolower($this->directories->kero_lib.$a.".php"))) {
                require_once(strtolower($this->directories->kero_lib.$a.".php"));
            } elseif (file_exists($this->directories->kero_lib.$a.".php")) {
                require_once($this->directories->kero_lib.$a.".php");
            } else {
                die("unable to load library on ".strtolower($this->directories->kero_lib.$a.".php x"));
            }
        }
    }
    
    /**
     * Session set value
     * @param mixed $a    value(s) want to saved to session
     */
    public function setKey($a)
    {
        $_SESSION[$this->config->saltkey]=$a;
    }
    
    /**
     * Session get saved value
     * @return mixed      Saved value(s) from session
     */
    public function getKey()
    {
        if (isset($_SESSION[$this->config->saltkey])) {
            return $_SESSION[$this->config->saltkey];
        } else {
            return 0;
        }
    }
    
    public function delKey()
    {
        unset($_SESSION[$this->config->saltkey]);
        session_destroy();
    }
    
    public function getcookie($var="")
    {
        if (empty($var)) {
            return 0;
        }
        if (isset($_COOKIE[$var])) {
            return $_COOKIE[$var];
        } else {
            return 0;
        }
    }
    
    public function setcookie($var="", $val="0")
    {
        $_COOKIE[$var] = $val;
    }
    
    /**
     * Show printed content of variable
     * @param  mixed $a     [description]
     */
    public function debug($a)
    {
        echo '<pre>';
        print_r($a);
        echo '</pre>';
    }
    
    /**
     * Show variable dump
     * @param  mixed $a     [description]
     */
    public function dd($a)
    {
        echo '<pre>';
        var_dump($a);
        echo '</pre>';
    }
    
    public function cdn_url($url="")
    {
        if ($this->config->environment == 'development' || empty($this->config->environment)) {
            return base_url($url);
        }
        if (strlen($this->config->cdn_url)>6) {
            return $this->config->cdn_url.$url;
        } else {
            return base_url($url);
        }
    }
    
    
    /**
     * Render buffered view to browser
     * @param  integer $cacheable true or false
     * @return void
     */
    public function render($cacheable=0)
    {
        $cacheable = (int) $cacheable;
        if ($cacheable) {
            $cache_filename = md5($_SERVER['REQUEST_URI']);
            $cache_file = SENECACHE.'/'.$cache_filename;
            if (file_exists($cache_file) && (filemtime($cache_file) > (time() - 60 * $cacheable))) {
                echo file_get_contents($cache_file);
            } else {
                $search = array(
                    '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
                    '/[^\S ]+\</s',     // strip whitespaces before tags, except space
                    '/(\s)+/s',         // shorten multiple whitespace sequences
                    '/<!--(.|\s)*?-->/' // Remove HTML comments
                );
                $replace = array(
                    '>',
                    '<',
                    '\\1',
                    ''
                );
                $pattern = '/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\')\/\/.*))/';
                $buffer = preg_replace($pattern, '', $this->__content);
                $buffer = preg_replace($search, $replace, $buffer);
                file_put_contents($cache_file, $buffer, LOCK_EX);
                echo $this->__content;
            }
            // At this point $cache is either the retrieved cache or a fresh copy, so echo it
        } else {
            echo $this->__content;
        }
    }
    
    // create abstract method index, so every controller has index method
    abstract public function index();
}
