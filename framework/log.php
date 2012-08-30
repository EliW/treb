<?php
/**
 * Log
 *
 * A basic class to deal with logging of information.
 *
 * Currently based upon 'text files', but designed to be enhanced in the future.
 *  (Perhaps to database tables?)
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class Log 
{
    // Constants for the various log levels.  Add more as needed:
    const ALWAYS = 101; // Bit of a hack, designed for non-error situations but that we always log
    const FATAL = 100;
    const ERROR = 90;
    const WARNING = 70;
    const INFO = 50;
    const DEBUG = 30;
    const EXTREME = 10;
    
    /**
     * write
     *
     * Writes a line to the logging mechanism
     *
     * $msg can be a string, or an array.  If a string it's just written as is.  If it's an
     *  Array, then it's automatically concatted w/ | at the moment
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $name Type of log, it's 'name' (turns into a filename)
     * @param mixed $msg The actual log message you want to save     
     * @param integer $level The log level, used to configure the amount of logging. (default INFO)
     * @return void
     * @access public
     **/
    static public function write($name, $msg, $level = 50) 
    {
        // Write this out to the appropriate location & file.
        $c = config();
        $dir = (string)$c->log->directory;
        if ($dir && (int)$c->log->level && !(int)$c->log->disable) {
            if ($level >= (int)$c->log->level) {
                $stamp = date('r');
                $out = is_array($msg) ? implode('|', $msg) : $msg;
                file_put_contents("{$dir}/{$name}.log", "[{$stamp}] {$out}\n", FILE_APPEND);            
            }
        }
    }
}
?>