<?php
/**
 * Cache
 *
 * A nice wrapper for our caching (Memcached, but it could be changed, maybe)
 *
 * It wraps Memcached so that we get a second level
 *  local cache, so a second request for the same cached data in one process doesn't
 *  go back to Memcached. -- As well as adding a few extra 'features'
 *
 * Uses the Singleton pattern for access
 *
 * WARNING:  This was originally written against 'memcache', and later updated to work
 *  with 'memcached'.  There are a number of features of 'memcached' that aren't fully supported
 *  or wrapped by this library.  This includes:
 *  * All the _ByKey methods
 *  * append & prepend
 *  * cas
 *  * fetch & getDelayed
 *  * setMulti
 *
 *  It's not that it can't be augmented to support some of those.  It's just that we weren't 
 *   using those yet when the conversion was done, so we didn't bother adding them at the moment.
 * 
 * @package treb
 * @author Eli White <eli@eliw.com>
 * @return object
 **/
 
function cache()
{
    return Cache::getConnection();
}

class Cache
{
    // Make some constants for easy verbal cache times:
    const MINUTE = 60;
    const HOUR = 3600;
    const DAY = 86400;
    const WEEK = 604800;
    const YEAR = 315360000;
    const FOREVER = 0;

    // Hold the singleton:
    private static $_singleton = NULL;
    
    /**
     * __construct
     *
     * Making this constructor private, even though it does nothing, to stop someone
     *  from directly instantiating a copy of Cache
     * 
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access public
     **/
    private function __construct() {}
    
    public static function getConnection()
    {
        $cachecfg = config()->cache;

        // Have we not instantiated a copy of cache yet?
        if (self::$_singleton === NULL) {
            // Wait, do we want to disable caching?
            if ((int)$cachecfg->disable) {
                self::$_singleton = new DisabledCacheConnection();
            } else {
                // use Xpath to read the server config out as array:
                $servers = $cachecfg->xpath("servers/server");
                self::$_singleton = new CacheConnection($servers, (string)$cachecfg->prefix);
            }
        }
        
        return self::$_singleton;
    }
}

/**
 * CacheConnection
 *
 * The actual connection class, does the work of Cache
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class CacheConnection
{
    // Private class properties we will use:
    private $_memcache = NULL;       // The actual memcached object
    private $_local_cache = array(); // Our local cache of data in this instance
    private $_prefix = '';           // What we will prefix all cache keys with
    private $_servers = false;       // Keep track of our servers
    
    // Disallowed endpoints:
    private $_disallowed = array('addByKey', 'appendByKey', 'casByKey', 'deleteByKey', 'getByKey',
        'getDelayed', 'getDelayedByKey', 'getMultiByKey', 'getServerByKey', 'prependByKey',
        'replaceByKey', 'setByKey', 'setMultiByKey', 'cas', 'append', 'prepend', 'setMulti',
        'fetch', 'fetchAll');
    
    /**
     * __construct
     *
     * Make a new CacheConnection.
     *
     * Pass in an array of SimpleXML elements as config as well as a key prefix.
     * 
     * @author Eli White <eli@eliw.com>
     * @param mixed $servers The server config needed
     * @param string $prefix A string to prefix all keys by.
     * @return CacheConnection
     * @access public
     **/
    public function __construct($servers, $prefix)
    {
        $this->_prefix = $prefix;
        $this->_servers = $servers;
       
        // Prepare the server list:
        $sarray = array();
        foreach ($servers as $s) {
            $sarray[] = array(
                (string) $s->host, // Host to connect to.
                (int) $s->port,    // Port, typically 11211
                (int) $s->weight,  // Bucket size / weight, how often is this one used?
                );
        }

        // Create the object, and set some options for us - May want to make these configurable
        $this->_memcache = new Memcached();
        $this->_memcache->setOption(Memcached::OPT_PREFIX_KEY, $prefix);
        //$this->_memcache->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_IGBINARY);
        
        // Now add the servers in:
        $this->_memcache->addServers($sarray);
    }
    
    /**
     * __call
     *
     * Generic 'call' functionality to allow any non-specificaly defined
     *  method call to be passed through to the underlying Memcached object.
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $name Name of the function we tried to call.
     * @param array $args Array of arguments that were passed.
     * @return mixed
     * @access public
     **/
    public function __call($name, $args)
    {
        // Pass most other calls through, but fail a few names that we don't support explicitly:
        if (in_array($name, $this->_disallowed)) {
            trigger_error("Cache endpoint {$name} not supported", E_USER_ERROR);
        }

        return call_user_func_array(array($this->_memcache, $name), $args);
    }

    /**
     * set
     *
     * A passthrough function to _update
     * 
     * @author Eli White <eli@eliw.com>
     * @param array $key The key name to save data as
     * @param mixed $value The value to save
     * @param integer $expire How many seconds should the data last?
     * @return boolean Did this setting of data work?
     * @access public
     **/
    public function set($key, $value, $expire = Cache::HOUR)
    {
        return $this->_update('set', $key, $value, $expire);
    }

    /**
     * add
     *
     * A passthrough function to _update
     * 
     * @author Eli White <eli@eliw.com>
     * @param array $key The key name to save data as
     * @param mixed $value The value to save
     * @param integer $expire How many seconds should the data last?
     * @return boolean Did this setting of data work?
     * @access public
     **/
    public function add($key, $value, $expire = Cache::HOUR)
    {
        return $this->_update('add', $key, $value, $expire);
    }

    /**
     * replace
     *
     * A passthrough function to _update
     * 
     * @author Eli White <eli@eliw.com>
     * @param array $key The key name to save data as
     * @param mixed $value The value to save
     * @param integer $expire How many seconds should the data last?
     * @return boolean Did this setting of data work?
     * @access public
     **/
    public function replace($key, $value, $expire = Cache::HOUR)
    {
        return $this->_update('replace', $key, $value, $expire);
    }

    /**
     * _update
     *
     * A wrapper function that handles all calls to memcached for us that
     *  would update a key.  Those being 'set', 'add', and 'replace'.
     *
     * This does a few things for us.  Such as setting a default cache of 1 hour, etc.
     *
     * It also creates and updates the 'local cache' so we don't ask for data twice
     *  from memcached within one PHP instance.
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $func The name of the memcached function that was called.
     * @param string $key The key name to save data as
     * @param mixed $value The value to save
     * @param integer $expire How many seconds should the data last?
     * @return boolean Did this setting of data work?
     * @access private
     **/
    private function _update($func, $key, $value, $expire = Cache::HOUR)
    {
        // Pass through the command to the underlying memcached:
        if ($result = $this->_memcache->{$func}($key, $value, $expire)) {
            // Update the local cache:
            $this->_local_cache[$key] = $value;                
        }

        return $result;
    }
    
    /**
     * increment
     *
     * A passthrough function to _math
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $key The key name to save data as
     * @param integer $by How much should this be incremented by?
     * @return integer What's the current value?
     * @access public
     **/
    public function increment($key, $by = 1)
    {
        return $this->_math('increment', $key, $by);
    }

    /**
     * decrement
     *
     * A passthrough function to _math
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $key The key name to save data as
     * @param integer $by How much should this be decremented by?
     * @return integer What's the current value?
     * @access public
     **/
    public function decrement($key, $by = 1)
    {
        return $this->_math('decrement', $key, $by);
    }
    
    /**
     * _math
     *
     * Similar to _update but wraps memcached's two mathematical methods:
     *  increment and decrement.
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $func The name of the memcached function that was called.
     * @param string $key The key name to do the math on
     * @param integer $by How much should this be changed by?
     * @return integer What's the current value?
     * @access private
     **/
    private function _math($func, $key, $by = 1) 
    {
        // Pass through the actual request
        $result = $this->_memcache->{$func}($key, $by);

        // Update the local cache if the request worked:
        if ($result !== FALSE) {
            $this->_local_cache[$key] = $result;
        }

        return $result;
    }
    
    /**
     * delete
     *
     * Similar to _update above, but designed just for 'delete'
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $key The key name to delete
     * @param integer $timeout Should there be a delay in how long it takes?
     * @return boolean Success
     * @access public
     **/
    public function delete($key, $timeout = 0) 
    {
        // Pass through the actual request
        if ($result = $this->_memcache->delete($key, $timeout)) {
            // It worked, remove the local cache:
            unset($this->_local_cache[$key]);
        }

        return $result;
    }

    /**
     * clearInternal
     *
     * Totally an optimization feature, but there are times when we don't need to have the
     *  internal cache stored for an item.  I realized that because of shallow copies, it's not
     *  initially a problem.  I originally built this fix by just making a separate FALSE param
     *  to every single method to say 'don't cache this internally'.  But then realized that this
     *  was cleaner for now, just a way after a potential internal cache hit, to say:  Yeah I
     *  don't need that now.
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $key The key name to remove internally
     * @return void
     * @access public
     **/
    public function clearInternal($key)
    {
        unset($this->_local_cache[$key]);
    }

    /**
     * get
     *
     * Generically a passthrough to memcached's 'get' that handles
     *  using the local cache, however to fully support multiget, it gets
     *  a weeee bit more complicated.  Especially since it needs to remove prefixing
     *  near the end.
     * 
     * @author Eli White <eli@eliw.com>
     * @param mixed $key The key name to retrieve.
     * @return mixed The value requested, or an array of values.
     * @access public
     **/
    public function get($key)
    {
        // If this is locally cached, just return it:
        if (isset($this->_local_cache[$key])) {
            $value = $this->_local_cache[$key];
        } else {
            // Attempt to read from Memcached:
            $value = $this->_memcache->get($key);
            if ($value !== FALSE) {
                $this->_local_cache[$key] = $value;
            }
        }
        
        return $value;
    }
     
     /**
      * getMulti
      *
      * See 'get' above.  This handles the case of 'multigets' for Get.  Rather complicated
      *  as it needs to look at the array, break out what parts we don't have cached
      *  then rebuild that cache as needed after making appropriate requests.  All while
      *  handing the key prefixes accordingly.
      * 
      * @author Eli White <eli@eliw.com>
      * @param array $key An array of keys to be retrieved
      * @return mixed The value requested
      * @access private
      **/
     private function getMulti(Array $key) 
     {
         // We have an array of keys, the first thing we need to do
         //  is determine how many of these we already have cached locally.
         //   ((Prepare for an overload of array_* methods))
         $values = array_intersect_key($this->_local_cache, array_flip($key));
         $remaining = array_keys(array_diff_key(array_flip($key), $values));

         // Do we have any left that we didn't previously find:
         if (count($remaining)) {
             // Now go ahead and make the call:
             $result = $this->_memcache->getMulti($remaining);
             if ($result) {
                 // Merge these values into both our return array and our cache
                 $this->_local_cache = array_merge($this->_local_cache, $result);
                 $values = array_merge($values, $result);
             }
         }

         return $values;
     }
    
    /**
     * lock
     *
     * Used to create user-space exclusive locks on data.  Pass it a key name
     *  you are going to want exclusive data to, and it will attempt to ensure
     *  that you will have it.  (As long as everyone plays well together)
     *
     * Defaults to a 1 second lock.  This shouldn't probably be changed unless
     *  it's to move it shorter, as this lock can stop people from getting their
     *  data, however, the capability exists to ask for different lengths in
     *  microseconds, in case you have a long running cronjob or something
     *  similar that needs longer.
     *
     * Hardcoded to sleep for 100ms between attempts, and to try 10 times.  We might
     *  want to move that to be configurable in the config() instead.
     *
     * It's up to the code that USES this to determine what it should do when a lock is
     *  not able to be obtained.  That is, whether to forge forward not caring, or to
     *  handle it's lack of a lock somehow.  (Such as a cron script just dying and not
     *  bothering to do any work until it runs again)
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $key The $key you are going to want an exclusive lock on
     * @return boolean Success
     * @access public
     **/
    public function lock($key, $duration = 1000000) 
    {
        $delay = 100000; // 100ms, 1/10 of a second
        $attempts = 10;
        do {
            // Try to create lock every 1/10th of a second until at max
            $success = $this->_update('add', "{$key}.lock", 1, $duration);
        } while (!$success && $attempts-- && usleep($delay));
        
        // Whether successful or not at this point, we aren't going to halt
        //  execution anymore.  Just proceed:
        return $success;
    }
    
    /**
     * unlock
     *
     * The opposite of above.  Just attempts to remove the lock previously granted:
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $key The $key you are going to want an exclusive lock on
     * @return boolean Success
     * @access public
     **/
    public function unlock($key) 
    {
        return $this->delete("{$key}.lock");
    }
    
    /**
     * sessionString
     *
     * Returns a string, based upon the $config, to be used by the memcached session handling
     * 
     * @author Eli White <eli@eliw.com>
     * @return string A memcached Session string
     * @access public
     **/
    public function sessionString() 
    {
        // Make an array full of tcp strings:
        $strings = array();
        foreach ($this->_servers as $s) {
            $strings[] = "{$s->host}:{$s->port}";
        }

        // Turn it into a CSV and return it.
        return implode(',', $strings);
    }
}

/**
 * DisabledCacheConnection
 *
 * Simple way to disable caching, while enabling all methods to still 'work' fine.
 *
 * All public methods need duplicated here currently.  (Could change this in the 
 *  future to only use a __method, but for now this still allows syntax checking)
 *
 * NOTE: Not bothering to phpdoc each of the methods:
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class DisabledCacheConnection extends CacheConnection
{
    public function __construct() {}
    public function __destruct() {}
    public function __call($name, $args) { return false; }
    public function set($key, $value, $expire = 0) { return false; }
    public function add($key, $value, $expire = 0) { return false; }
    public function replace($key, $value, $expire = 0) { return false; }
    public function increment($key, $by = 0) { return false; }
    public function decrement($key, $by = 0) { return false; }
    public function delete($key, $timeout = 0) { return false; }
    public function get($key) { return false; }
    public function getMulti(Array $key) { return false; }
    public function lock($key, $duration = 0) { return false; }
    public function unlock($key) { return false; }
    public function sessionString() { return false; }
}
?>