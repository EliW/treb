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
        //  NOTE: Currently this yes, will parse the config.xml on every pageload.
        //   (Well, every load where config() is called).  For a typical website this isn't
        //   a huge burden.  If it becomes that, you could refactor this to parse once and
        //   store the parsed version in APC, for immediate access.  However you then need
        //   to deal with having a way to check for .xml file changes, and/or manually push
        //   a reparse if you need to change the config.

        // Works by parsing a 'config.xml' from a 'config' directory of the application.
        if (!($xml = file_get_contents(ROOT . '/config/config.xml'))) {
            die("Cannot read site configuration file!");
        }

        // Parse the XML into a configuration - Switch off errors & entities, and revert to 
        //  original settings afterwards to not affect any other code on the site.
        $error_state = libxml_use_internal_errors(true);
        $entity_state = libxml_disable_entity_loader(true);
        $this->_data = simplexml_load_string($xml);
        if ($this->_data === FALSE) { die("Configuration Unparseable"); }
        libxml_use_internal_errors($error_state);
        libxml_disable_entity_loader($entity_state);
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
