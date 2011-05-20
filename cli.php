<?php
/**
 * TrafexCrawler
 *
 * @category   Crawler
 * @package    Cli
 * @copyright  Copyright (c) 2011 Tim de Pater <code@trafex.nl>
 * @license    GPLv3 License http://www.gnu.org/licenses/gpl.txt
 * @version    $Id:$
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Amsterdam');
set_include_path('.:application/:library/');
require_once 'Bootstrap.php';

// Prepare for command line parsing
try {
    $console = new Zend_Console_Getopt(array(
        'help' => 'Displays usage information',
    	'start' => 'Start the crawler',
    	'stop' => 'Stop the crawler',
    	'status' => 'Check if crawler is running',
        'statistics' => 'Return statistics',
        'profile' => 'Enable the XHProf profiler',
        'verbose' => 'Enable verbose ouput'
    ));
    $console->parse();
} catch (Zend_Console_Getopt_Exception $exception) {
    failure($exception->getMessage());
}


// Display help information
if (isset($console->help) || count($console->toArray()) == 0) {
    failure($console->getUsageMessage());
}
if (isset($console->profile)) {
    App_Profiler::start();
}
if (isset($console->statistics)) {
    var_dump(SitesBase::getStats());
    exit;
}

$crawler = new Crawler();

if (isset($console->start)) {
    //try {
        if (isset($console->debug)) {
            $crawler->setDebug();
        }
        if (isset($console->verbose)) {
            $crawler->setVerbose();
        }
        $crawler->start();
        $console->status = true;
    /*} catch (Exception $e) {
        failure($e->getMessage());
    }*/
}

if (isset($console->stop)) {
    $crawler->stop();
    $console->status = true;
}

if (isset($console->status)) {
    if ($crawler->isRunning()) {
        echo 'Crawler is running, pid: ' . $crawler->getPid() . PHP_EOL;
        while ($crawler->isRunning()) {
            // This process must be running
            sleep(1);
        }
    } else {
        echo 'Crawler is stopped' . PHP_EOL;
    }
}
if (isset($console->profile)) {
    App_Profiler::end();
}
