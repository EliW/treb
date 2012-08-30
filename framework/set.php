<?php
/**
 * Set
 *
 * This is to be a generic class, designed to allow easy wants to generate
 *  sets of Models.  IE, if you need to read in a bunch of rows at once,
 *  but obviously want the results cached to some degree, as well as
 *  having each result that you iterate over, being the appropriate Model
 *  class.
 *
 * NOTE: Potential TODO.  Currently this creates each model instance as you
 *  request it.  It might be worthwhile to keep a second array of models
 *  So that if you choose to iterate over a set twice, you get the same
 *  model returned to you, instead of a brand new one.
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class Set implements Iterator, ArrayAccess, Countable
{
    // A single const used to declare when a set should NOT be cached:
    const NOCACHE = NULL;
    
    // Storage variables for us to keep track of our own internal data:
    private $_model;   // Name of our model
    private $_query;   // SQL query
    private $_pool;    // DB Pool to use.
    private $_key;     // Cache key
    private $_timeout; // Cache timout
    private $_set;     // Actual set/array of data IDs

    /**
     * __construct
     *
     * Basic constructor for the Set class.
     *
     * You need to pass in the name of the Model you wish to use, and a DB query
     *  that will return id's (and only id's) of that model.
     *
     * You also have the option to provide the DB pool that your query should be
     *  run against. If you don't, it defaults to the pool used by the Model that
     *  you referenced.  There are cases however where you might need to enter a
     *  different pool however.  Such as if you are querying against a 'Jobs'
     *  table, but what you are pulling back are 'Users' ids, and therefore want
     *  User Model to be applied.  An edge case perhaps, but good to cover it.
     *
     * NOTE: The $query parameter is a little bit magic.  You can pass in a string
     *  or an array.  If you pass in an array, it assumes you are doing a boundQuery
     *  and that the 1st element is the query, and the second is an array of parameters.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $model The Class Name of the model that this query will pull IDs of.
     * @param mixed $query Either a query (as string) or boundQuery (as array)
     * @param string $pool Name of the pool to access (defaults to the Model's pool)
     * @param int $timeout What the cache timeout of this Set should be
     * @return Set
     * @access public
     **/
    public function __construct($model, $query, $pool = NULL, $timeout = Cache::HOUR)
    {
        // This class supports delayed instantiation.  Allowing you to create a
        //  set, but then if you don't actually USE the set, it doesn't bother
        //  creating it.  So upon construct, just store the data:
        $this->_model = $model;
        $this->_query = $query;
        $this->_timeout = $timeout;
        $this->_set = NULL;

        // Determine the key, based upon query
        $stub = is_array($query) ? md5($query[0] . '|' . implode('|',$query[1])) : md5($query);
        $this->_key = "Set:{$model}:{$stub}";

        // Figure out the pool to use:
        $this->_pool = $pool ?: $model::pool();
    }

    /**
     * _loadSet
     *
     * Does the actual instantiation work of loading the set from cache/db/etc
     *
     * NOTE: Also checks that it doesn't do the work a second time, so that this
     *  can be called on every method to ensure that the set exists
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access private
     **/
    private function _loadSet()
    {
        // Only continue if we don't have a set currently:
        if ($this->_set === NULL) {
            // First attempt to load this set from the cache -> maybe ... 
            if ($this->_timeout !== self::NOCACHE) {
                $cache = cache();
                $set = $cache->get($this->_key);
                if ($set !== FALSE) {
                    $this->_set = $set;
                    return; // Short circuit, we are done
                }
            }
            
            // Else, either we didn't want cache, or it wasn't in cache, either way:

            // Get it from the database:
            if (is_array($this->_query)) {
                $result = db($this->_pool)->boundQuery($this->_query[0], $this->_query[1]);
            } else {
                $result = db($this->_pool)->query($this->_query);
            }

            // Figure out our data now:
            if (!$result) {
                $this->_set = array();
            } else {
                $this->_set = $result->fetchAll(PDO::FETCH_COLUMN);
            }

            // And force this into the cache IF WE ARE CACHING:
            if ($this->_timeout !== self::NOCACHE) {
                $cache->set($this->_key, $this->_set, $this->_timeout);
            }
        }
    }

    /**
     * getAll
     *
     * Not normally the recommended access pattern for a Set, you should use the
     *  ArrayAccess or Iterator methods for doing that.  However, sure, sometimes
     *  you just need to "Get Them All"
     *
     * @author Eli White <eli@eliw.com>
     * @return array Full of 'Model' based objects
     * @access public
     **/
    public function getAll()
    {
        $this->_loadSet();
        $all = array();
        foreach ($this->_set as $id) {
            $all[] = new $this->_model($id);
        }
        return $all;
    }

    /**
     * getIds
     *
     * Just returns all the Ids, a few use cases where this is all you really needed, and
     *  therefore you don't need to create all the models yet.
     *
     * @author Eli White <eli@eliw.com>
     * @return array Full of IDs
     * @access public
     **/
    public function getIds()
    {
        $this->_loadSet();
        return $this->_set;
    }

    /**
     * getSlice
     *
     * Returns a slice of the Set as an array, starting at $offset and
     * including $length objects. If length is empty, it returns to the end of the set.
     * If offset is negative, it is assumed to be relative to the end of the set.
     *
     * @author Oscar Merida <oscar@mojolive.com>
     * @param int $offset
     * @param int $length
     * @return array Full of 'Model' based objects
     * @access public
     */
    public function getSlice($offset, $length = NULL) {
        $this->_loadSet();
        $ids = array_slice($this->_set, $offset, $length);

        // init empty and return something to prevent
        // other errors.
        $slice = array();
        foreach ($ids as $id) {
            $slice[] = new $this->_model($id);
        }

        return $slice;
    }

    /**
     * getShard
     *
     * Returns a 'sharded' variety array.  Similar to how getAll() and getSlice() above work.
     *  but in this case, you are providing a divisor and comparison value.  This in effect gives
     *  you a 'shard' of the set.  By returning only those rows that match:
     *   (($id % $divisor) == $comparison)
     *
     * @author Eli White <eli@eliw.com>
     * @param int $comparison The integer value to compare the modulus against
     * @param int $divisor How many 'shards' you are wanting, the Modulus factor.
     * @return array Full of 'Model' based objects
     * @access public
     */
    public function getShard($comparison, $divisor) {
        $this->_loadSet();

        // Init empty and return something to prevent other errors.
        $results = array();
        foreach ($this->_set as $id) {
            // Now only include our 'shard' of data from throughout the set.
            if (($id % $divisor) == $comparison) {
                $results[] = new $this->_model($id);
            }
        }

        return $results;
    }

    /**
     * bust
     *
     * Allows you to bust the cache of a Set.  Note because of delayed instantiation
     *  that you can create the set, and bust it, without ever touching the DB.
     *
     * @author Eli White <eli@eliw.com>
     * @return boolean
     * @access public
     **/
    public function bust()
    {
        return cache()->delete($this->_key);
    }

    /**
     * count
     *
     * <Implements Countable> Returns a count of how many items are in this set.
     *
     * @author Eli White <eli@eliw.com>
     * @return int
     * @access public
     * @see Countable::count()
     **/
    public function count()
    {
        $this->_loadSet();
        return count($this->_set);
    }

    /**
     * offsetExists
     *
     * <Implements ArrayAccess> Returns a Boolean if said offset exists.
     *
     * @author Eli White <eli@eliw.com>
     * @param int $offset The array offset you want
     * @return boolean
     * @access public
     * @see ArrayAccess::offsetExists()
     **/
    public function offsetExists($offset)
    {
        $this->_loadSet();
        return array_key_exists($offset, $this->_set);
    }

    /**
     * offsetGet
     *
     * <Implements ArrayAccess> Returns the value of the set at the specified offset
     *
     * @author Eli White <eli@eliw.com>
     * @param int $offset The array offset you want
     * @return mixed
     * @access public
     * @see ArrayAccess::offsetGet()
     **/
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return new $this->_model($this->_set[$offset]);
        } else {
            return false;
        }
    }

    /**
     * offsetSet
     *
     * <Implements ArrayAccess> Would normally allow a value to be set via array
     *  access.  However you shouldn't be changing the values of a Set this way
     *  as it could lead to issues.  Therefore, this is a nullop
     *
     * @author Eli White <eli@eliw.com>
     * @param int $offset The array offset you want to set
     * @param mixed $value The value we are going to throw away
     * @return void
     * @access public
     * @see ArrayAccess::offsetSet()
     **/
    public function offsetSet($offset, $value) {}

    /**
     * offsetUnset
     *
     * <Implements ArrayAccess> Like above, would normally allow one to unset a
     *  value, but you shouldn't be doing that in a Set, so nullop
     *
     * @author Eli White <eli@eliw.com>
     * @param int $offset The array offset you want to set
     * @return void
     * @access public
     * @see ArrayAccess::offsetUnset()
     **/
    public function offsetUnset($offset) {}

    /**
     * current
     *
     * <Implements Iterator> Returns the current element of the Set
     *
     * @author Eli White <eli@eliw.com>
     * @return Model The appropriate Model subclass
     * @access public
     * @see Iterator::current()
     **/
    public function current() {
        $this->_loadSet();
        return new $this->_model(current($this->_set));
    }

    /**
     * key
     *
     * <Implements Iterator> Returns the key of the current element in the Set
     *
     * @author Eli White <eli@eliw.com>
     * @return int
     * @access public
     * @see Iterator::key()
     **/
    public function key() {
        $this->_loadSet();
        return key($this->_set);
    }

    /**
     * next
     *
     * <Implements Iterator> Moves the pointer to the next element in the Set
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access public
     * @see Iterator::next()
     **/
    public function next() {
        $this->_loadSet();
        next($this->_set);
    }

    /**
     * rewind
     *
     * <Implements Iterator> Rewinds the current set for repeat iteration:
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access public
     * @see Iterator::rewind()
     **/
    public function rewind() {
        $this->_loadSet();
        reset($this->_set);
    }

    /**
     * valid
     *
     * <Implements Iterator> Rewinds the current set for repeat iteration:
     *
     * @author Eli White <eli@eliw.com>
     * @return boolean
     * @access public
     * @see Iterator::valid()
     **/
    public function valid() {
        $this->_loadSet();
        return (key($this->_set) !== NULL);
    }

    /**
     * getResult
     *
     * Return the raw set without models. For manipulating large sets before having
     * actual Model instances. Use with caution.
     *
     * @author Oscar Merida <oscar@mojolive.com>
     * @return null
     */
    public function getResult()
    {
        $this->_loadSet();
        return $this->_set;
    }

} // END class
?>