<?php
/**
 * Server
 *
 * Meant to hold 'server related' utility methods.  Stuff like finding someone's IP,
 *  determining a browser perhaps, looking at the request details, etc.
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class Server
{
    /**
     * getIP
     *
     * Return the end user's IP address, dealing with proxies/etc
     *
     * @author Eli White <eli@eliw.com>
     * @return string User's IP Address
     * @access public
     **/
    static public function getIP()
    {
        $ip = '';

        // First check if this is a forwarded/proxied request:
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            // Only grab the original request from the string if so:
            $ip = preg_replace("/^.*, /", '', $ip);
        }

        // Otherwise default to the REMOTE_ADDR
        if (!strlen($ip) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Worst case, give us something:
        if (!strlen($ip)) {
            $ip = '0.0.0.0';
        }

        return $ip;
    }

    /**
     * getAgent
     *
     * Returns an HTTP User Agent, or NONE.  Used in a number of places where we always
     *  want 'something', and would prefer 'NONE' to a blank entry
     *
     * @author Eli White <eli@eliw.com>
     * @return string A User's HTTP agent, or 'NONE'
     * @access public
     **/
    static public function getAgent()
    {
        return (empty($_SERVER['HTTP_USER_AGENT']) ? 'NONE' : $_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * method
     *
     * Returns the HTTP method that was used to make this request.
     *
     * Defaults to 'GET' if it can't figure it out
     *
     * @author Eli White <eli@eliw.com>
     * @return string The access method used.
     * @access public
     **/
    static public function method()
    {
        $method = 'GET';
        if (!empty($_SERVER['REQUEST_METHOD'])) {
            $method = $_SERVER['REQUEST_METHOD'];
        }
        return $method;
    }

    /**
     * isPost
     *
     * Boolean response version of the method() above.  Just let's you know
     *  If this request was a post or not.
     *
     * @author Eli White <eli@eliw.com>
     * @return boolean
     * @access public
     **/
    static public function isPost()
    {
        return (self::method() == 'POST');
    }

    /**
     * isAjax
     *
     * Attempts to determine if this is a proper ajax request.
     * NOTE:  This relies on the fact we are using jQuery.
     *
     * @author Eli White <eli@eliw.com>
     * @return boolean
     * @access public
     **/
    static public function isAjax()
    {
        $answer = false;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $answer = ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');
        }
        return $answer;
    }

    /**
     * getUrl
     *
     * Returns the current URL that you are currently on
     *
     * @author Eli White <eli@eliw.com>
     * @return string The current URL
     * @access public
     **/
    static public function getUrl()
    {
        return (empty($_SERVER['REQUEST_URI']) ? '/' : $_SERVER['REQUEST_URI']);
    }

    /**
     * getUrlStub
     *
     * Returns the current 'stub' (IE, first 'directory' of the URL)
     *
     * @author Eli White <eli@eliw.com>
     * @return string The current URL stub
     * @access public
     **/
    static public function getUrlStub()
    {
        $path = explode('/', self::getUrl(), 3);
        return empty($path[1]) ? '' : $path[1];
    }


} // END class
?>