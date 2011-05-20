<?php
/**
 * TX-Spider
 *
 * @category   Crawler
 * @package    Application
 * @copyright  Copyright (c) 2011 Tim de Pater
 * @license    http://www.gnu.org/licenses/gpl.txt GPLv3 License
 * @version    $Id:$
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Amsterdam');

/**
 * Convenience function for exiting on failure
 *
 * @param string $message
 * @return void
 */
function failure($message)
{
    echo $message . PHP_EOL;
    exit(200); // Non-zero value denotes an error, above 128 is user defined
}

// Autoload the ZF classes
function zendFrameworkAutoload($class)
{
    require_once str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
}
spl_autoload_register('zendFrameworkAutoload');

require_once 'Spider.php';
require_once 'CoucheDb.php';