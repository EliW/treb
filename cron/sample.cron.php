<?php
/**
 * A sample of how you can build a cron file.
 *
 * @package treb
 * @author Eli White <eli@eliw.com>
 **/

// Issue the following just to bootstrap a cron:
require_once __DIR__.'/../framework/application.php';
$app = new Application('cron');

// For a cron you might need to remove the time limit:
set_time_limit(0);

// Now let's do something:
$date = new DateTime();
echo $date->format(DateTime::RSS);
