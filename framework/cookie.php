<?php
/**
 * Cookie
 *
 * Just a basic holding class of static methods to handle setting/clearing of cookies for us
 *  I hate doing these tasks by hand, because there are all the 'little things' that you need
 *  to remember to do.  This makes it easier and uses some of our configuration for us.
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class Cookie
{
    /**
     * set
     *
     * Sets a cookie value for us.
     *
     * Adds a few functionalities over the default PHP cookie handling:
     * # Handles setting domain for you
     * # Defaults the path to '/' instead of 'current directory'
     * # If $expire is less than time(), it assumes you want to add the value to time()
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed  Documentation
     * @return void
     * @access public
     **/
    public static function set($name, $value, $expire = 0, $path = '/')
    {
        // Determine the final expiration
        $now = time();
        if ($expire && ($expire < $now)) {
            $expire += $now;
        }
        
        // Add in the prefix if it exists:
        if ($prefix = config()->cookies->prefix) {
            $name = "{$prefix}_{$name}";
        }
        
        // Now we can just set the cookie:
        setcookie($name, $value, $expire, $path, (string)config()->env->domain);
    }
    
    /**
     * delete
     *
     * Removes a cookie for us.  Well, actually sets the cookie to expire.  I dislike
     *  how cookies work.
     * 
     * @author Eli White <eli@eliw.com>
     * @param mixed  Documentation
     * @return void
     * @access public
     **/
    public static function delete($name, $path = '/')
    {
        // Cheating.  All the logic we need is in 'set', so why bother repeating it:
        //  Set the cookie to expire 2 days ago (why 2 days?  why not?)
        self::set($name, 0, -172800, $path);
    }
    
    /**
     * get
     *
     * Returns the value of a cookie, taking prefixing into account
     * 
     * @author Eli White <eli@eliw.com>
     * @param string $name The name of the cookie you want the value of
     * @return mixed
     * @access public
     **/
    public static function get($name)
    {
        // Add in the prefix if it exists:
        if ($prefix = config()->cookies->prefix) {
            $name = "{$prefix}_{$name}";
        }
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : NULL;
    }
    
} // END class 
?>