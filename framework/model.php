<?php
/**
 * Model
 *
 * An abstract class that is used to handle data coming to/from the database.
 *
 * To use this, simply extend it, and define the table name that this Model will operate
 *  over.  Yes, this could be done via just passing in a parameter, but that doesn't
 *  give you the benefits of a dedicated class per 'table'.  Also, you can then
 *  extend the model to add any methods that should operate over this data.
 *
 * In general, any/all DB queries that refer to a specific
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
abstract class Model
{
    // Our table config.  Subclasses must define this:
    static protected $_table;

    // Config for DB pools - subclasses should overwrite these as needed
    static protected $_write = 'write';
    static protected $_read = 'read';

    // Cache timeout configuration - subclasses can override if needed
    static protected $_timeout = Cache::HOUR;

    // Various internal properties that we will use:
    private $_id = NULL;
    private $_data = array();    // The actual data array
    private $_dirty = array();   // Remembers if any values are dirty (changed)
    private $_touched = array(); // Remembers when any value has been touched
    private $_sql = array();     // Knows if any data updates are SQL based.

    /**
     * __construct
     *
     * Basic constructor, tries to load up an existing ID data, or assume a new one:
     *
     * @author Eli White <eli@eliw.com>
     * @param integer $id The ID of an existing table row, if it exists.
     * @return Model class
     * @access public
     **/
    public function __construct($id = NULL)
    {
        if ($id !== NULL) {
            $this->_id = (int)$id;
            $this->_loadObject();
        }
    }

    /**
     * __get
     *
     * Implements the 'getter' magic method functionality of PHP.  Allows direct
     *  access of data points.  Just a passthrough to our existing 'get' method.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $field The field/column name
     * @return mixed
     * @access public
     **/
    public function __get($field)
    {
        // Just call our existing get() method:
        return $this->get($field);
    }

    /**
     * get
     *
     * Actual method used to retrieve data from the object/array.  Has a special
     *  use though, you can call it without any parameter, and it returns the full
     *  array of data.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $field The field/column name
     * @return mixed
     * @access public
     **/
    public function get($field = NULL)
    {
        // If no field was asked for, give them the whole array
        if ($field === NULL) {
            return $this->_data;
        }

        // If they wanted a specific field, make sure it exists before returning it:
        if (array_key_exists($field, $this->_data)) {
            return $this->_data[$field];
        }

        // If they got here, they tried to access a field that doesn't exist.
        //   Currently fails 'gracefully' by returning NULL, so that it's ok if you
        //   try to access a field in a 'new' object that isn't saved yet.  Does mean
        //   that you can try to access 'weird stuff' and just get NULL back.

        // A lof of code depends on this silently returning NULL if you try to access
        // a field that doesn't actually exists. Uncommenting this line can help when
        // debugging.
        // trigger_error("Field $field does not exist.", E_USER_NOTICE);
        return NULL;
    }

    /**
     * __set
     *
     * Magic setter method for PHP.  Allows for direct property setting.
     *
     * Note, because of the way this works, this will not allow the setting of
     *  raw SQL statements.  The only way to do that, is via the 'set' command below:
     *
     * @author Eli White <eli@eliw.com>
     * @param string $field The field/column name you want to set the value of.
     * @param mixed $value The value you wish it set to.
     * @return void
     * @access public
     **/
    public function __set($field, $value)
    {
        // Just call the built in set method:
        $this->set($field, $value);
    }

    /**
     * set
     *
     * Sets a field.  Taking care of some fancy logic for us while it's at it.
     *  for one thing, it actually tries to keep track if the data really changed
     *  or not, and therefore if it's 'dirty' and needs saved later.
     *
     * Secondly, it has a bit of a 'hack' to make something work.  There are times
     *  when you need to use some RAW SQL.  Such as doing an increment, or setting
     *  NOW().  For those cases, there is the 3rd parameter.  Set it to true and
     *  it means you are passing it raw SQL, and to not ignore that fact.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $field The field/column name you want to set the value of.
     * @param mixed $value The value you wish it set to.
     * @param boolean $sql Is this RAW sql and should be treated as such?
     * @return void
     * @access public
     **/
    public function set($field, $value, $sql = false)
    {
        // First of all, regardless mark this field as 'touched'.  This is useful in some
        //  use cases to know it was touched, even if it doesn't get dirty (such as modified_on)
        $this->_touched[$field] = TRUE;

        // Only truly 'set' this field, and mark it as dirty, if it's changed:
        //
        //  We have to get tricky here. Because PHP and MySQL treat things a little
        //  differently.  We want to consider '0' and 0 (or '1' and 1) to be
        //  identical, so we need to compage via $field != $value to check for dirty.
        //
        //  HOWEVER, we do want someone to be able to say NULL and that means NULL
        //  But at the same time, NULL should NOT equal 0 or '0'  So we have to check
        //  separately for NULL
        if ($sql || !array_key_exists($field, $this->_data) || ($this->_data[$field] != $value) ||
                (($value === NULL) && ($this->_data[$field] !== NULL)) ||
                (($value !== NULL) && ($this->_data[$field] === NULL))) {
            // Update the field and set it dirty:
            $this->_dirty[$field] = TRUE;
            $this->_data[$field] = $value;

            // If this was a SQL statement, remember that, otherwise make sure and clear it:
            if ($sql) {
                $this->_sql[$field] = TRUE;
            } else {
                unset($this->_sql[$field]);
            }
        }
    }

    /**
     * __isset
     *
     * Allows for basic 'isset' checks to happen via PHP method methods:
     *
     * @author Eli White <eli@eliw.com>
     * @param string $field The field/column name you want to check.
     * @return boolean
     * @access public
     **/
    public function __isset($field)
    {
        return isset($this->_data[$field]);
    }

    /**
     * setAll
     *
     * A helper function.  Just allows you to pass in a whole array of key/value
     *  pairs and allow that to be set.  It makes it much easier if you are
     *  'copying' data from one to another.  You can also pass in an
     *  optional array of field names to 'ignore'.  Making it easy to
     *  for example copy everything except the 'created_on' and 'modified_on'
     *
     * @author Eli White <eli@eliw.com>
     * @param Array $data An associative array of key/value pairs to set.
     * @param Array $ignore A simple array of key names to not bother setting.
     * @return void
     * @access public
     **/
    public function setAll(Array $data, Array $ignore = array())
    {
        foreach ($data AS $field => $value) {
            if (!in_array($field, $ignore)) {
                $this->set($field, $value);
            }
        }
    }

    /**
     * save
     *
     * Actually does the work of saving the data back to the database, handling
     *  caching as it goes:
     *
     * @author Eli White <eli@eliw.com>
     * @return mixed NULL (for nothing done), FALSE (for error), PDOStatement (for success)
     * @access public
     **/
    public function save()
    {
        // Don't do anything if we don't have any dirty values:
        if (count($this->_dirty)) {
            // We have dirty values.  That good (I guess).  But now we need to
            //  update our created_on and modified_on values automatically.
            if ($this->_id) {
                // Row already exists, just update modified via direct setting so it's
                //  cached appropriately - However don't do this if a programmer touched
                //  the modified_on field in any way, even if as a nullop
                if (empty($this->_touched['modified_on'])) {
                    $this->set('modified_on', self::formatDateTime('now'));
                }
            } else {
                // New row anyway, will need reloaded, safe to use NOW():
                $this->set('created_on', 'NOW()', true);
                $this->set('modified_on', 'NOW()', true);
            }

            // Connect to the master DB:
            $db = db(static::$_write);

            // Prepare to store our update clauses:
            $updates = array();
            $reload = false;

            // Loop through every dirty field:
            foreach (array_intersect_key($this->_data, $this->_dirty) as $field => $value) {
                // If this was a 'sql' request, enter it raw, and mark to reload the data.
                if (!empty($this->_sql[$field])) {
                    $updates[] = "`{$field}` = {$value}";
                    $reload = true;
                } else if ($value === NULL) {
                    // A Null should be reflected as such:
                    $updates[] = "`{$field}` = NULL";
                } else {
                    // Everything else will be quoted:
                    $updates[] = "`{$field}` = " . $db->quote($value) . "";
                }
            }

            // At this point we can clear the dirty, touched and sql arrays:
            $this->_touched = array();
            $this->_dirty = array();
            $this->_sql = array();

            // Don't continue if we really didn't have any updates:
            if (empty($updates)) { return FALSE; }

            // Prepare the SQL for the update/insert
            $sql = implode(', ', $updates);

            // Decide if we need to do an UPDATE or INSERT:
            $table = static::$_table;
            if ($this->_id) {
                $result = $db->query(
                    "UPDATE `{$table}`
                     SET {$sql}
                     WHERE `id` = '{$this->id}'
                     LIMIT 1
                    ");
            } else {
                $result = $db->query(
                    "INSERT INTO `{$table}`
                     SET {$sql}
                    ");
                $this->_id = $db->lastInsertId();

                // Anytime we create a new row, we need to immediately read it back from the DB
                //  to ensure that our data matches the data of any SQL defaults.
                $reload = true;
            }

            //  Force a refresh of data from the DB if needed:
            if ($reload) {
                $this->_data = $this->_readData(true);
            }

            // Just before returning, take last step of being write-through cache
            //  and forcibly update the cache instance with the new data:
            //  NOTE:  Assumes that no DB triggers are going to be messing with the data.
            cache()->set($this->_key(), $this->_data, static::$_timeout);

            return $result;

        }

        return NULL;
    }

    /**
     * delete
     *
     * Actually removes the row from the database.  Boom.  Gone.
     *
     * NOTE: Leaves the object in a
     *
     * @author Eli White <eli@eliw.com>
     * @return mixed FALSE for failure, PDO result otherwise
     * @access public
     **/
    public function delete()
    {
        // If this hasn't ever been saved, then don't bother deleting:
        if (!$this->_id) {
            return FALSE;
        }

        // Delete it from the database:
        $table = static::$_table;
        $result = db(static::$_write)->query(
            "DELETE FROM `{$table}`
             WHERE `id` = '{$this->id}'
             LIMIT 1
            ");

        // Only clear this out, if the delete command was successful:
        if ($result && $result->rowCount()) {
            // Remove the cached object
            $this->bust();

            // Clear out this object to make it 'clean'.
            //  WARNING:  Leaves this in a 'weird' state, because object exists but
            //  is really a new 'blank' object now.
            $this->_clear();
        }

        return $result;
    }

    /**
     * _key
     *
     * Just returns the cache key, created in a consistent manner:
     *
     * @author Eli White <eli@eliw.com>
     * @return string The cache key
     * @access public
     **/
    private function _key()
    {
        return (static::$_table . ':' . $this->_id);
    }

    /**
     * _readData
     *
     * Pulls the raw data from the database and returns it.  Optionally allows the
     *  Master DB to be referenced if needed for immediate re-reads of data to catch
     *  trigger or raw SQL updates.
     *
     * @author Eli White <eli@eliw.com>
     * @return Array of values ... As per PDO::FETCH_ASSOC
     * @access private
     **/
    private function _readData($master = false)
    {
        // Prepase to store the data:
        $data = false;

        // Only attempt this if we actually have an ID though:
        if ($this->_id) {
            $pool = $master ? static::$_write : static::$_read;
            $table = static::$_table;
            $data = db($pool)->query(
                    "SELECT *
                     FROM `{$table}`
                     WHERE `id` = '{$this->_id}'")->fetch(PDO::FETCH_ASSOC);
        }

        return $data;
    }

    /**
     * __loadObject
     *
     * Handles a couple of things for us.  This attempts to load the object
     *  from cache, or if that fails, from the database.
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access private
     **/
    private function _loadObject()
    {
        // Only do anything if we have an ID, else nullop
        if ($this->_id) {
            // First attempt to load this from cache:
            $data = cache()->get($this->_key());

            // If it worked, save it, else try to read it from the DB
            if ($data !== FALSE) {
                $this->_data = $data;
            } else if ($data = $this->_readData()) {
                // We got it from the database, so let's cache it:
                $this->_data = $data;
                cache()->set($this->_key(), $this->_data, static::$_timeout);
            } else {
                // Data doesn't exist.  We can't have that.
                throw new ModelException('Data nonexistent');
            }
        }
    }

    /**
     * bust
     *
     * Explicitly busts the cache of this one object.  Should be rarely used externally
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access public
     **/
    public function bust()
    {
        cache()->delete($this->_key());
    }

    /**
     * _clear
     *
     * Clears all data out of this, so that it can be reinitialized.  Edge use cases
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access protected
     **/
    protected function _clear()
    {
        $this->id = NULL;
        $this->_data = array();
        $this->_dirty = array();
        $this->_touched = array();
        $this->_sql = array();
    }

    /**
     * isDirty
     *
     * Just a simpler programatic way for an extended model to check if something is
     *  dirty or not without the 'dirty' feeling of directly touching the array.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $name The name of the data field we want to check
     * @return boolean
     * @access public
     **/
    public function isDirty($name)
    {
        return (isset($this->_dirty[$name]) && $this->_dirty[$name]);
    }

    /**
     * pool
     *
     * A semi-workaround method.  A static method that returns the database pool that
     *  this model is using.  Exists primarily so that 'Set' class can use this to figure
     *  out the pool to access based upon the Model in use.
     *
     * @author Eli White <eli@eliw.com>
     * @return string The database pool of the 'read'
     * @access public
     **/
    static public function pool()
    {
        return static::$_read;
    }

    /**
     * formatDate
     *
     * Formats a timestamp, a date string, or a DateTime object into the format needed for
     *  this model for storage (IE, mySQL's format by default).
     *
     * You 'CAN' pass in a 2nd parameter for a format.  But that kinda defeats the point.
     *  mainly that exists so that formatDateTime can do good code re-use.
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $date A unix/php timestamp
     * @return string A MySQL formatted version.
     * @access public
     **/
    public static function formatDate($date, $format = 'Y-m-d')
    {
        if (!($date instanceof DateTime)) {
            $date = new DateTime(is_numeric($date) ? "@{$date}" : $date);
        }
        return $date->format($format);
    }

    /**
     * formatDateTime
     *
     * Formats a timestamp, a date string, or a DateTime object into the format needed for
     *  this model for storage (IE, mySQL's format)
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $time A unix/php timestamp
     * @return string A MySQL formatted version.
     * @access public
     **/
    public static function formatDateTime($time)
    {
        return self::formatDate($time, 'Y-m-d H:i:s');
    }

    /**
     * getTable
     *
     * Return the name of the table that stores this model. Provides read-only access.
     *
     * @author Oscar Merida <oscar@mojolive.com>
     * @return string
     * @access public
     */
    static public function getTable()
    {
        $class = get_called_class();
        return $class::$_table;
    }

    /**
     * total
     *
     * An 'odd' method to be sure, but it has a few use cases, for this model
     *  just count how many of them are in the database in total, generically.
     *
     * You can optionally pass in a 'since' and 'before' clauses as well, to get the
     *  total over a certain period of time.  These are standard 'mixed' time objects
     *  to be either datetime, a timestamp, or a time description string:
     *
     * WARNING:  No Caching at the moment!
     *
     * @author Eli White <eli@eliw.com>
     * @param mixed $since A mixed time designation
     * @param mixed $before A mixed time designation
     * @return integer The total count
     * @access public
     **/
    static public function total($since = NULL, $before = NULL)
    {
        // Prepare the clauses, if we need to:
        $restrict = array();
        if ($since) {
            $time = self::formatDateTime($since);
            $restrict[] = "created_on > '{$time}'";
        }
        if ($before) {
            $time = self::formatDateTime($before);
            $restrict[] = "created_on < '{$time}'";
        }
        $restrict = $restrict ? (" WHERE ".implode(' AND ', $restrict)) : '';

        // Now just get the count:
        $table = static::$_table;
        $result = db(static::$_read)->query("SELECT count(*) FROM `{$table}` {$restrict}");
        return $result ? $result->fetchColumn() : 0;
    }

}

/**
 * ModelException
 *
 * Just a basic extension of the exception for more detailed try/catch blocks
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class ModelException extends Exception {}
