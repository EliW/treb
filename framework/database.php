<?php
/**
 * Database
 *
 * This is the database wrapper class, and all its extended glory.
 *
 * Really it's just a thin wrapper around PDO, that does a few things that experience
 *  has taught me are key for scability:
 *
 * 1) Handles the concept of database pools (as well as the simpler concept of just
 *    read vs write)
 * 2) Log database errors, so that you can see them and deal with them.
 *
 * Uses the Multiton pattern.  A singleton per DB pool, combining pools as needed
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 * @param string $pool
 * @return PDO
 **/

function db($pool = false)
{
    return Database::getConnection($pool);
}

class Database
{
    // Private array for holding the multitons
    private static $_multitons = array();

    /**
     * __construct
     *
     * Making the constructor private so that noone can instantiate this class
     *
     * @author Eli White <eli@eliw.com>
     * @return void
     * @access private
     **/
    private function __construct() {}

    /**
     * getConnection
     *
     * Meat of this class.  getConnection currently handles setting up
     *  the pools, and connecting to the server.  It then passes the PDO connection
     *  to DatabaseConnection to be 'wrapped' for future use.
     *
     * NOTE: I considered making this mimic the Cache class more, and pushing more of
     *  the 'connection' concept down into the 'wrapper' itself, but it seemed more
     *  logical to break it this way for databases.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $pool The name of the DB pool to access
     * @return DatabaseConnection
     * @access public
     **/
    public static function getConnection($pool = false)
    {
        // Read in the configuration once, default to he DB section:
        $config = config()->db;

        // Figure out what pool to use, if they don't set one, or if they
        //  don't choose a 'valid' one according to config, we are going to
        //  push them into the default one defined in the config:
        if (!$pool || !isset($config->pools->{$pool})) {
            $pool = (string)$config->default;
        }

        // See if the current connection exists in our Multiton array
        //  Only keep working if it doesn't:
        if (!array_key_exists($pool, self::$_multitons)) {
            // Grab all the servers from SimpleXML - Use xpath to get a consistant Array back.
            $servers = $config->xpath("pools/{$pool}/server");

            // Keep trying to select a database, looping for failed connections:
            $connection = null;
            while (!$connection && count($servers)) {
                // Pick a database randomly from the pool
                $key = array_rand($servers);

                // Try to make the connection via PDO:
                try {
                    $connection = new PDO($servers[$key]->dsn, $servers[$key]->user, $servers[$key]->pass);
                } catch (PDOException $e) {
                    // It failed, log this:
                    Log::write('database',
                        array($pool, $servers[$key]->dsn, $e->getCode(), $e->getMessage()),
                        Log::ERROR);
                }

                // If we didn't make a valid connection, then remove this server to try again:
                if (!$connection) { unset($servers[$key]); }
            }

            // If after all that looping, we NEVER got a connection, that's a major issue:
            if (!$connection) {
                Log::write('database', array('ALL CONNECTIONS FAILED', $pool), Log::FATAL);
                throw new Exception("Unable to connect to any {$pool} database!");
            } else {
                // Instantiate the Database connct, and save to multions:
                self::$_multitons[$pool] = new DatabaseConnection($connection);
            }
        }

        return self::$_multitons[$pool];
    }
}

/**
 * DatabaseConnection
 *
 * Basically a wrapper to go around PDO.  Handles logging-n-stuff for us at the
 *  same time.
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/
class DatabaseConnection
{
    // Hold the actual database connection for us:
    private $_db = NULL;

    /**
     * __construct
     *
     * A very basic constructor.  Just store the database connection.
     * Also issue a UTF8 command to ensure we are working in utf8
     *
     * @author Eli White <eli@eliw.com>
     * @param PDO $connection The PDO object
     * @return void
     * @access public
     **/
    public function __construct(PDO $connection)
    {
        $this->_db = $connection;
    }

    /**
     * __call
     *
     * This magic method allows for all methods that we don't override, to pass
     *  straight through to PDO for us.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $name Name of the PDO method to be called
     * @param string $args All the arguments we were passed
     * @return mixed (Whatever PDO returns)
     * @access public
     **/
    public function __call($name, $args)
    {
        // Create passthrough to PDO functions...
        return call_user_func_array(array($this->_db, $name), $args);
    }

    /**
     * query
     *
     * Specifically overloading PDO::query to add in our custom error handling:
     *
     * @author Eli White <eli@eliw.com>
     * @param string $sql The SQL statement to be executed - always required
     * @param variable Using var_args to handle any number of parameters passed in
     * @return PDOStatement Results of the query
     * @access public
     **/
    public function query($sql)
    {
        // Get any additional parameters that were passed in, using varargs
        $args = func_get_args();

        // Call the PDO query method, saving the response:
        $result = call_user_func_array(array($this->_db, 'query'), $args);

        // If the query failed we had an error, pass that into our error handler
        if (!$result) { $this->_handleError($sql, $this->_db->errorInfo()); }

        return $result;
    }

    /**
     * exec
     *
     * Just like 'query' above, but handles exec instead.  Could 'almost' be combined
     *  codewise, but easier to separate for now:
     *
     * @author Eli White <eli@eliw.com>
     * @param string $sql SQL statement to be executed.
     * @return integer The number of rows that were affected.
     * @access public
     **/
    public function exec($sql)
    {
        // Actually pass through the exec request (no parameters)
        $result = $this->_db->exec($sql);

        // And again, if it fails, handle the error
        if ($result === FALSE) { $this->_handleError($sql, $this->_db->errorInfo()); }

        return $result;
    }

    /**
     * boundQuery
     *
     * The above functions work well for logging, but remove the ability to do
     *  prepare/execute based queries, which are much easier to use to keep things
     *  SQL injection safe.
     *
     * The problem is that if you do that using PDO, you can't actually do our logging
     *  that I want.  Because you run most of the commands on the return value, not
     *  directly.
     *
     * So this is the workaround solution.  A middle-solution.  You provide it a
     *  parameterized query, and an array of values in any PDO legal way.  This function
     *  then takes care of the magic for you, of preparing and binding.  But does so in
     *  a way that allows us to still do logging.
     *
     * This doesn't let you prepare one statement once, then execute it with numerous
     *  sets of parameters yet.  But in practice that's a RARE use case in a web app.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $sql The SQL statement to be executed
     * @param array $params An array of parameters to be bound into the statement
     * @return PDOStatement The result object
     * @access public
     **/
    public function boundQuery($sql, array $params)
    {
        // Pass through the prepare statement:
        $statement = $this->_db->prepare($sql);

        // Attempt to execute it now:
        $success = false;
        if ($statement) {
            $success = $statement->execute($params);
        }

        // If this didn't work, log the error again.
        // THOUGHT: Maybe this should log the params as well?
        if (!$success) { $this->_handleError($sql, $statement->errorInfo()); }

        return $statement;
    }

    /**
     * _handleError
     *
     * If we had any DB error, handle that, logging and throwing an exception.
     *
     * @author Eli White <eli@eliw.com>
     * @param string $sql The original SQL that was executed
     * @param array $info An errorInfo array, gathered from PDO or PDOStatement.
     * @return void
     * @access private
     **/
    private function _handleError($sql, array $info)
    {
        // Log this, any DB error is unacceptable:
        Log::write('database', array('Query Error', $sql, $info[0], $info[1], $info[2]), Log::FATAL);

        // Now throw an exception, include the specific error:
        throw new Exception("Database Error: <pre>ERROR: \n{$info[2]} \n\nSQL: \n{$sql}</pre>");
    }

    /**
     * fixes "special" items for LIKE clauses
     *
     * @param string $string
     * @return string quoted like string
     */
    public function quoteLike($string)
    {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\%', '\_'), $string);
    }
}
