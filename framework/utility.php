<?php
/**
 * Utility
 *
 * A utility class.  Every project seems to need one.  Consider this the 'dumping ground'
 *  of all those fun little utility functions that needed to live somewhere.  Here that is
 *
 * Yes, lots of these things could be broken up into their own classes.  But for now
 *  this is simpler.  We can get cleaner later if we feel the need.
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class Utility
{
    /**
     * generateToken
     *
     * Generates a CSRF protection token, as needed, or returns an existing one for reuse
     * Automatically puts it in the session on your behalf.
     *
     * NOTE:  Assumes that a session has already been started.  Don't try using this
     *  without a valid session!
     *
     * @author Eli White <eli@eliw.com>
     * @return string CSRF Token string
     * @access public
     **/
    private static $_tokenID = false;
    public static function generateToken()
    {
        // If we don't already have a token for this request:
        if (!self::$_tokenID) {
            // Check if the session has one for us:
            // If the token already exists and hasn't expired just keep using it:
            $token = empty($_SESSION['token']) ? false : $_SESSION['token'];
            $expires = empty($_SESSION['tokenExpires']) ? false : $_SESSION['tokenExpires'];
            if (!$token || ($expires < time())) {
                // Generate a new code:
                $token = md5(uniqid(mt_rand(), true));

                // Set it into the session & locally
                $_SESSION['token'] = $token;
            }

            // Save this locally for future requests now:
            self::$_tokenID = $token;

            // No matter what, generate a new timestamp 4 hours in the future.
            // The longer you set this, the less chance of an 'annoying' situation
            //  where you had a window open, went back to it, and then it didn't let
            //  you do the task you were trying to do.
            // The shorter you set this, the more security you gain against brute force
            //  attacks.  Current time is a compromise.
            $ttl = (int)config()->security->csrf->ttl ?: 14400;
            $_SESSION['tokenExpires'] = time() + $ttl;
        }

        // Return the code, so it can be included in the POST
        return self::$_tokenID;
    }

    /**
     * checkToken
     *
     * Given a submitted value from the user to check against, ensures it
     *  matches the token in the current session, for CSRF protection.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $check The submitted token to check
     * @return boolean
     * @access public
     **/
    public static function checkToken($check)
    {
        // Read in all data from the session:
        $token = empty($_SESSION['token']) ? false : $_SESSION['token'];
        $expires = empty($_SESSION['tokenExpires']) ? false : $_SESSION['tokenExpires'];

        // Token needs to exist, expires needs to be in the future, and token needs to match.
        if ($token && ($token == $check) && ($expires > time())) {
            return true;
        }

        return false;
    }

    /**
     * setMessage
     *
     * Persist messages between pages via $_SESSION
     *
     * @param string $key
     * @param string $message
     * @return void
     * @author Oscar Merida <oscar@mojolive.com>
     */
    public static function setMessage($key, $message) {
        if (!array_key_exists('messages', $_SESSION)) {
            $_SESSION['messages'] = array();
        }
        $_SESSION['messages'][$key] = $message;
    }

    /**
     * getMessage
     *
     * Returns persisted message, escapes it, and removes it from $_SESSION.
     *
     * @param $key
     * @param boolean $purge
     * @return string
     * @todo Figure return if final if fails
     */
    public static function getMessage($key, $purge = TRUE) {
        $msg = NULL;

        if (isset($_SESSION['messages'][$key])) {
            $msg = $_SESSION['messages'][$key];

            if (TRUE == $purge) {
                unset($_SESSION['messages'][$key]);

                // try to keep $_SESSION clean
                if (empty($_SESSION['messages'])) {
                    unset($_SESSION['messages']);
                }
            }
        }

        return $msg;
    }

    /**
     * cachedRow
     *
     * A semi-complicated method, intended to make this rather common coding task
     *  straight forward.  Allows you to specify a DB query, and a cache name, and have
     *  it take care of doing a cache lookup, DB lookup, cache set, return data process
     *  all for you.
     *
     * Assumes at the moment that you will only ever want a single-first-row returned to you
     *  Afterall, otherwise you are probably doing something custom, or should be using
     *  the Set class.
     *
     * Has a fair bit of configuration details as well, to be flexible enough to be used
     *  in many different situations:
     *
     * @author Eli White <eli@eliw.com>
     * @param string $sql The actual SQL to run, should plan on returning 1 row
     * @param Array $params We are assuming this to be a bound query, include params here.
     * @param mixed $db DB config, can be an instance of Database, or a pool, or false.
     * @param string $key The cache key, if not provided one will be generated for you.
     * @param integer $timeout How long you want the cache to last.  Defaults to 1 hour
     * @param Cache $cache A cache instance, if you already had one you wanted to provide
     * @param boolean $force Ignores the cache, reads fresh from the database then pushes to cache
     * @return SimpleObject|bool
     * @access public
     **/
    public static function cachedRow($sql, $params = NULL, $db = NULL,
                                     $key = NULL, $timeout = Cache::HOUR,
                                     $cache = NULL, $force = FALSE)
    {
        // If they didn't provide a cache connection, make one:
        if (!($cache instanceof CacheConnection)) {
            $cache = cache();
        }

        // If they didn't provide the key to use, autogenerate one:
        if (!($key)) {
            $unique = $params ? ($sql . '|' . implode($params, ',')) : $sql;
            $key = "cachedRow|" . md5($unique);
        }

        // See if we have a cached copy of this result:
        $row = $force ? FALSE : $cache->get($key);
        if (($row === FALSE) || ($row === '')) { // Cache miss or empty row
            // Figure out our DB connection:
            if (!($db instanceof DatabaseConnection)) {
                $db = db($db);
            }

            // Query the database (use appropriate method based upon bound query or not:)
            if ($params) {
                $result = $db->boundQuery($sql, $params);
            } else {
                $result = $db->query($sql);
            }

            // Check for valid results, and if so, read the row in:
            //  NOTE:  This will NOT currently cache a false lookup, in case the data
            //         is added in the near future.  Modify this if it's needed later
            //         for performance purposes.
            if ($result && ($row = $result->fetchObject())) {
                $cache->set($key, $row, $timeout);
            }
        }

        return $row;
    }

} // END class
