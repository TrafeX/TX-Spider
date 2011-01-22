<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Amsterdam');

require_once 'src/Spider.php';
require_once 'src/Database.php';

$spider = new SiteSpider();
$spider->run();
