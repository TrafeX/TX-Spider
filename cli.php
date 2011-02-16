<?php
/**
 * TrafexCrawler
 *
 * @category   Spider
 * @package    Cli
 * @copyright  Copyright (c) 2011 Tim de Pater <code@trafex.nl>
 * @license    GPLv3 License http://www.gnu.org/licenses/gpl.txt
 * @version    $Id:$
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Amsterdam');
set_include_path('.:src/');
require_once 'src/Bootstrap.php';

// Prepare for command line parsing
try {
    $console = new Zend_Console_Getopt(array(
        'help' => 'Displays usage information',
    	'run' => 'Start the spider',
        'statistics' => 'Return statistics',
        'debug' => 'Enable the XHProf profiler',
        'verbose' => 'Enable verbose ouput'
    ));
    $console->parse();
} catch (Zend_Console_Getopt_Exception $exception) {
    failure($exception->getMessage());
}

// Display help information?
if (isset($console->help) || count($console->toArray()) == 0) {
    failure($console->getUsageMessage());
}
if (isset($console->debug)) {
    // start profiling
    include_once '/var/www/xhprof_lib/utils/xhprof_lib.php';
    include_once '/var/www/xhprof_lib/utils/xhprof_runs.php';
    xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY + XHPROF_FLAGS_NO_BUILTINS);
}
if (isset($console->statistics)) {
    var_dump(SitesBase::getStats());
    exit;
}
if (isset($console->run)) {
    // Run spider
    try {
        $spider = new SiteSpider();
        if (isset($console->debug)) {
            $spider->setDebug();
        }
        if (isset($console->verbose)) {
            $spider->setVerbose();
        }
        $spider->run();
    } catch (Exception $e) {
        failure($e->getMessage());
    }
}
if (isset($console->debug)) {
    // end profiling
    $profiler_namespace = 'tx-spider';  // namespace for your application
    $xhprof_data = xhprof_disable();
    $xhprof_runs = new XHProfRuns_Default();
    $run_id = $xhprof_runs->save_run($xhprof_data, $profiler_namespace);
    echo PHP_EOL;
    printf('http://localhost/xhprof_html/index.php?run=%s&source=%s', $run_id, $profiler_namespace);
    echo PHP_EOL;
}