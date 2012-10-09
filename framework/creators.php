<?php
/** 
 * Creators
 *
 * The following methods are defined here to be universally available.  They provide functional
 *  access to class creators of the framework, without having to hard-load those classes.
 *
 * (Originally these lived in the appropriate class files.  And all the class files were
 *  hard-loaded, a refactor of the code moved more features/classes into the 'framework' directory
 *  and as part of that all of the framework was made to be auto-loaded -- which gives/gave the
 *  benefit of not needing, say, to load the database methods, if a request doesn't even need the
 *  database.  However doing this meant that these functions weren't defined and available from the
 *  beginning.)
 **/

/**
 * db
 *
 * Returns a multiton instantiation of the Database class
 * 
 * @author Eli White <eli@eliw.com>
 * @param string $pool Optional database pool to choose
 * @return DatabaseConnection
 **/
function db($pool = NULL)
{
    return Database::getConnection($pool);
}

/**
 * cache
 *
 * Returns a singleton instantiation of the Cache class
 * 
 * @author Eli White <eli@eliw.com>
 * @return CacheConnection
 **/
function cache()
{
    return Cache::getConnection();
}

/**
 * config
 *
 * Returns a singleton instantiation of the Config class
 * 
 * @author Eli White <eli@eliw.com>
 * @param string $pool Optional database pool to choose
 * @return SimpleXML
 **/
function config()
{
    return Config::get();
}
