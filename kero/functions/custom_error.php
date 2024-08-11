<?php
/**
* Global functions that use by accross application
*
* @author Daeng Rosanda
* @version 4.0.3
*
* @package SemeFramework\Kero\Functions
* @since 4.0.3
*/

/**
* Customize Error handling
*
* @param  int $errno         Error number
* @param  string $errstr        Error string (message)
* @param  string $error_file    Error occured on
* @param  int $error_line    occurences line number in a file
*/
function seme_error_handling($errno, $errstr, $error_file, $error_line)
{
    if (isset($_SERVER['argv'])) {
        $backtraces = debug_backtrace();
        $bct = array();
        $fls = array();

        if (!defined('SEME_VERBOSE')) {
            $fls = array('index.php','sene_controller.php','sene_model.php','sene_engine.php','sene_mysqli_engine.php','runner_controller.php');
        }

        $ef = explode('/', str_replace('\\', '/', $error_file));
        if (isset($ef[count($ef)-1])) {
            $ef = $ef[count($ef)-1];
        }
        if (in_array(strtolower($ef), $fls)) {
            $error_file = '';
            $error_line = '';
        }
        $i=0;
        $bcts = array();
        foreach ($backtraces as $bts) {
            if (!isset($bts['file'])) {
                continue;
            }
            $bcts[] = $bts;
            $filename = explode('/', str_replace('\\', '/', $bts['file']));
            if (isset($filename[count($filename)-1])) {
                $filename = $filename[count($filename)-1];
            }
            $bts['filename'] = $filename;
            if (!in_array(strtolower($filename), $fls)) {
                if ($i<=2 && (empty($error_file) || empty($error_line))) {
                    $error_file = $bts['file'];
                    $error_line = $bts['line'];
                }
                $bct[]= $bts;
            }
            $i++;
        }
        if (empty($error_file) || empty($error_line)) {
            $error_file = $bcts[0]['file'];
            $error_line = $bcts[0]['line'];
        }
        $error_file = substr($error_file, strlen(SEMEROOT));
        print '================= ERROR ===================='.PHP_EOL;
        print $error_file.''.PHP_EOL;
        print 'Line: '.$error_line.PHP_EOL;
        print 'Error: ['.$errno.'] '.$errstr.''.PHP_EOL;
        $error_file = substr($error_file, strlen(SEMEROOT));
        print '--------------------------------------------'.PHP_EOL;
        print 'Backtrace: ---------------------------------'.PHP_EOL;
        $i=0;
        foreach ($bct as $e) {
            $i++;
            if ($i<=-1) {
                continue;
            }
            if (!isset($e['file'])) {
                continue;
            }
            $e['file'] = substr($e['file'], strlen(SEMEROOT));
            print $i.'. File: '.$e['file'].PHP_EOL;
            print 'Line: '.$e['line'].PHP_EOL;
            if (isset($e['class'])) {
                print 'Class: '.$e['class'].PHP_EOL;
                print 'Method: '.$e['function'].PHP_EOL;
            } else {
                print 'Function: '.$e['function'].PHP_EOL;
            }
        }
        print '=========== Seme Framework v'.SEME_VERSION.' ============'.PHP_EOL;
    } else {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        $backtraces = debug_backtrace();
        $bct = array();
        $fls = array();
        if (!defined('SEME_VERBOSE')) {
            $fls = array('index.php','sene_controller.php','sene_model.php','sene_engine.php','sene_mysqli_engine.php');
        }

        $ef = explode('/', str_replace('\\', '/', $error_file));
        if (isset($ef[count($ef)-1])) {
            $ef = $ef[count($ef)-1];
        }
        if (in_array(strtolower($ef), $fls)) {
            $error_file = '';
            $error_line = '';
        }
        $i=0;
        $bcts = array();
        foreach ($backtraces as $bts) {
            if (!isset($bts['file'])) {
                continue;
            }
            $bcts[] = $bts;
            $filename = explode('/', str_replace('\\', '/', $bts['file']));
            if (isset($filename[count($filename)-1])) {
                $filename = $filename[count($filename)-1];
            }
            $bts['filename'] = $filename;
            if (!in_array(strtolower($filename), $fls)) {
                if ($i<=2 && (empty($error_file) || empty($error_line))) {
                    $error_file = $bts['file'];
                    $error_line = $bts['line'];
                }
                $bct[]= $bts;
            }
            $i++;
        }
        if (empty($error_file) || empty($error_line)) {
            $error_file = $bcts[0]['file'];
            $error_line = $bcts[0]['line'];
        }

        echo '<div style="padding: 10px; background-color: #ededed;">';
        echo '<h2 style="color: #ef0000;">Error</h2>';
        echo '<p>File: '.$error_file.'</p>';
        echo '<p>Line: '.$error_line.'</p>';
        echo "<p><b>Error:</b> [$errno] $errstr<br></p>";
        echo '</div>';
        echo '<div style="padding: 20px; border: 1px #dddddd solid; font-size: smaller;">';
        echo "<h3>Backtrace</h3>";
        echo '</div>';
        echo '<div style="padding: 20px; border: 1px #dddddd solid; font-size: smaller;">';
        $i=0;
        foreach ($bct as $e) {
            $i++;
            if ($i<=-1) {
                continue;
            }
            if (!isset($e['file'])) {
                continue;
            }
            echo '<p><b>File</b>: '.$e['file'].'</p>';
            echo '<p><b>Line</b>: '.$e['line'].'</p>';
            if (isset($e['class'])) {
                echo '<p><b>Class</b>: '.$e['class'].'</p>';
                echo '<p><b>Method</b>: '.$e['function'].'</p>';
            } else {
                echo '<p><b>Function</b>: '.$e['function'].'</p>';
            }

            echo '<hr>';
        }
        echo '</div>';
        echo "<hr><p><small>Seme Framework v".SEME_VERSION." Error Handler</small></p>";
    }
}

// register error handler
set_error_handler("seme_error_handling");
