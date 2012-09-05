<?php
/**
 * Config
 *
 * A very basic configuration class.  Reads in config, parses it, allows it to be used.
 *
 * Uses singleton pattern - Because I want to.
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 * @return SimpleXML
 **/
function config()
{
    return Config::get();
}

class Config
{
    private static $_object; // Holds the singleton
    private $_data; // Holds the config data

    /**
     * __construct
     *
     * Constructor, private since it's a singleton, reads the config file.
     *
     * NOTE:  Assumes that a define of ROOT has happened, else it doesn't know where to look
     *
     * NOTE:  If it can't get the config, it dies with a VERY rough error
     *   message.  This is really meant for testing purposes, since you would
     *   NEVER put a live website up sans config file, right?
     *
     * We may want to pretty that up in the future
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access private
     **/
    private function __construct()
    {
        // Works by parsing a 'config.xml' from a 'config' directory of the application.
        if (!file_exists(ROOT . '/config/config.xml')) {
            die("Site Configuration Missing");
        }

        // Parse the XML into a configuration.
        //  NOTE: If performance becomes a concern (currently it's not) then the parsed
        //  version should be cached in APC, and either a manual 'reparse' mechanism
        //  put in place, or a stat check happen each time for changes.  But for now,
        //  that's overkill. (Also it could at that point be smart and ignore a bad
        //  config change that was made and keep running)
        libxml_use_internal_errors(true);
        $this->_data = simplexml_load_file(ROOT . '/config/config.xml');
        if ($this->_data === FALSE) {
            die("Configuration Unparseable");
        }
    }

    /**
     * __clone
     *
     * PHP's clone method.  But we don't want to allow cloning, so make it private
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access private
     **/
    private function __clone() {}

    /**
     * get
     *
     * The static method 'get' will ensure the singleton exists and then
     * return the data from it.
     *
     * @author Eli White <eli@eliw.com>
     * @return SimpleXML tree of config data
     * @access public
     **/
     static public function get()
     {
         $o = self::singleton();
         return $o->_data;
     }

     /**
      * singleton
      *
      * This singleton method instantiates the instance and returns it.
      *
      * @author Eli White <eli@eliw.com>
      * @return Config object
      * @access public
      **/
     static public function singleton()
     {
         if (!is_object(self::$_object)) {
             self::$_object = new self();
         }
         return self::$_object;
     }
}
