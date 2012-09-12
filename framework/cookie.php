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
     * # Defaults the 'httponly' protection to be on.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $name Name for the cookie (will be prefixed by any cookie prefix)
     * @param mixed $value The value you wish to set
     * @param integer $exp How long until this cookie expires (seconds), or a timestamp.
     * @param string $path The path this cookie will be available from (default '/')
     * @param boolean $sec Is this for secure connections only? (default false)
     * @param boolean $http Should this be for http connections only? (default true)
     * @return void
     * @access public
     **/
    public static function set($name, $value, $exp = 0, $path = '/', $sec = false, $http = true)
    {
        // Prep our config:
        $config = config();
        
        // Determine the final expiration
        $now = time();
        if ($exp && ($exp < $now)) {
            $exp += $now;
        }

        // Add in the prefix if it exists:
        if ($prefix = $config->cookies->prefix) {
            $name = "{$prefix}_{$name}";
        }

        // Now we can just set the cookie:
        setcookie($name, $value, $exp, $path, (string)$config->env->domain, $sec, $http);
    }

    /**
     * delete
     *
     * Removes a cookie for us.  Well, actually sets the cookie to expire.  I dislike
     *  how cookies work.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $name Name for the cookie
     * @param string $path The path this cookie will be available from (default '/')
     * @param boolean $sec Is this for secure connections only? (default false)
     * @param boolean $http Should this be for http connections only? (default true)
     * @return void
     * @access public
     **/
    public static function delete($name, $path = '/', $sec = false, $http = true)
    {
        // Cheating.  All the logic we need is in 'set', so why bother repeating it:
        //  Set the cookie to expire 2 days ago (why 2 days?  why not?)
        self::set($name, 0, -172800, $path, $sec, $http);
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
