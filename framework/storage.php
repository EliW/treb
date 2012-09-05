<?php
/**
 * Storage
 *
 * Just a generic storage class.  Basically mimics what stdClass provides but allows
 *  for future enhancement, and more importantly allows for data to be stored/retrieved
 *  in a manner that won't cause a NOTICE error if the data is attempted to be accessed
 *  prior to instantiation, but just return NULL.
 *
 * Also allows an optional array to be passed in, and if so, it's used as the initial state
 *  of the object, allowing for quick instantiation of lots of values.
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class Storage implements Countable
{
    // Where it all gets held:
    protected $_data;

    /**
     * __construct
     *
     * Basic constructor
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access public
     **/
    public function __construct(Array $input = NULL)
    {
        if (!$input) {
            $input = array();
        }
        $this->_data = $input;
    }

    /**
     * __set
     *
     * Magic Method: Allow any property to be set
     *
     * @author Eli White <eli@eliw.com>
     * @param $name string The property name passed in
     * @param $value mixed The value you want to save
     * @return void
     * @access public
     **/
    public function __set($name, $value)
    {
        $this->_data[$name] = $value;
    }

    /**
     * __get
     *
     * Magic Method:  Allow any property to be retrieved
     *
     * @author Eli White <eli@eliw.com>
     * @param $name string The name of the property to be retrieved
     * @return mixed The value requested
     * @access public
     **/
    public function &__get($name)
    {
        $return = null;
        if (array_key_exists($name, $this->_data)) {
            return $this->_data[$name];
        }
        return $return;
    }

    /**
     * __isset
     *
     * Magic Method:  A way to check if a value is set.
     *
     * @author Eli White <eli@eliw.com>
     * @param $name string The name of the property to be checked
     * @return boolean
     * @access public
     **/
    public function __isset($name)
    {
        return isset($this->_data[$name]);
    }

    /**
     * __unset
     *
     * Magic Method:  Remove a property
     *
     * @author Eli White <eli@eliw.com>
     * @param $name string The name of the property to be removed
     * @return void
     * @access public
     **/
    public function __unset($name)
    {
        unset($this->_data[$name]);
    }

    /**
     * count
     *
     * Used to implement Countable.  Returns the number of registered properties:
     *
     * @author Eli White <eli@eliw.com>
     * @return integer
     * @access public
     **/
    public function count()
    {
        return count($this->_data);
    }
}
