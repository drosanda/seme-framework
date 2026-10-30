<?php
/**
 * Seme Framework logging class
 * Default log file name is seme.log
 * Default location
 * - root if write permission enable
 * - app/cache if write permission enable
 */
class Seme_Log {
  public $directory = '';
  public $filename = 'seme.log';
  public $path = '';
  /** Also write to stderr so it appears in `docker logs` */
  public $stderr = true;

  public function __construct(){
    $this->resolvePath();
  }

  /**
   * Pick first writable location: SEMEROOT, then SENECACHE
   */
  private function resolvePath(){
    $this->filename = basename($this->filename);
    if(empty($this->dir))
    foreach([SEMEROOT, SENECACHE] as $dir){
      $this->directory = rtrim($dir, '/\\');
      $this->path = $this->directory.DIRECTORY_SEPARATOR.$this->filename;
      if(!file_exists($this->path)) @touch($this->path);
      if(is_writable($this->path)) return;
    }
  }

  /**
   * Change filename of log file
   * @param  string $filename new file name
   * @return object           this class
   */
  public function changeFilename($filename){
    $this->filename = $filename;
    $this->resolvePath();
    return $this;
  }

  /**
   * Write message to log
   * @param  string $str      log message
   * @return object           this class
   */
  public function write($str){
    $line = date("Y-m-d H:i:s").' - '.$str.PHP_EOL;
    @file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    if($this->stderr) @file_put_contents('php://stderr', $line);
    return $this;
  }

  /**
   * Get path of log file
   * @return string           full path
   */
  public function getPath(){
    return $this->path;
  }
}
