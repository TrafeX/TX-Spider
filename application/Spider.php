<?php
/**
 * TrafexCrawler
 *
 * @category   Crawler
 * @package    Application
 * @copyright  Copyright (c) 2011 Tim de Pater <code@trafex.nl>
 * @license    GPLv3 License http://www.gnu.org/licenses/gpl.txt
 * @version    $Id:$
 */

/**
 * The spider that crawls the websites
 *
 */
class Crawler extends ZendX_Console_Process_Unix
{
    /**
     * Debug mode
     *
     * @var bool
     */
    protected $_debug = false;

    /**
     * Verbose mode
     *
     * @var bool
     */
    protected $_verbose = false;

    /**
     * Constructs the class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Set the debug mode
     *
     * @param bool $debug
     */
    public function setDebug($debug = true)
    {
        $this->_debug = $debug;
    }

    /**
     * Set the verbose mode
     *
     * @param bool $verbose
     */
    public function setVerbose($verbose = true)
    {
        $this->_verbose = $verbose;
    }

    /**
     * Run the spider
     *
     * @return void
     */
    protected function _run()
    {
        $urls = SitesBase::getToBeParsed();
        if (count($urls) == 0) {
            SitesBase::addSite('http://www.enrise.com', 0, 0);
            $this->_log('No sites found, starting fresh');
        }
        $this->_parse();
    }

    /**
     * Parse loop
     *
     * @return void
     */
    protected function _parse()
    {
        $urls = SitesBase::getToBeParsed();
        // @todo: Use iterator
        foreach ($urls as $website) {
            $website = $website->value;
            $parseTime = microtime(true);
            $this->_log(
                sprintf('Parsing "%s" (id: %s, hop: %u)', $website->url,
                $website->_id, $website->hops)
            );
            $data = $this->_getData($website->url);
            $urls = array();
            if (null !== $data['content']) {
                $urls = $this->_getUrls($data['content']);
                foreach ($urls as $url) {
                    SitesBase::addSite($url, ($website->hops + 1), $website->_id);
                }
            }
            SitesBase::addMetadata($website->_id, $data['code'], round((microtime(true) - $parseTime), 4), count($urls));
        }

        // Unset to safe memory, but does it work?
        unset($website, $parseTime, $data, $urls);
        $this->_log(sprintf('Memory usage: %.2fMB', memory_get_usage(true) / 1024 / 1024));

        // we don't loop in debug mode
        if (!$this->_debug)
        {
            $this->_parse();
        }
    }

    /**
     * Retrieve the data from the url
     *
     * @param array $url
     */
    protected function _getData($url)
    {
        $data = array('code' => 404, 'content' => null);
        $config = array('useragent' => "TX-Spider", 'maxredirects' => 5,
        'timeout' => 5);
        try {
            $client = new Zend_Http_Client($url, $config);
            $request = $client->request();
            $data['code'] = $request->getStatus();
        } catch (Exception $e) {
            $this->_log(
            sprintf('Failed opening "%s" (code: %u)', $url, $data['code']));
            return $data;
        }
        if (! $request->isSuccessful()) {
            $this->_log(
            sprintf('Failed opening "%s" (code: %u)', $url, $data['code']));
            return $data;
        }
        $content = $request->getBody();
        if ($content && strlen($content) > 0) {
            $data['content'] = $content;
        } else {
            $this->_log(
            sprintf('Failed reading content from url "%s" (code: %u)', $url,
            $data['code']));
        }
        // memory savings
        unset($client, $request, $content);
        return $data;
    }

    /**
     * Retrieve urls from the given data
     *
     * @param array $data
     */
    protected function _getUrls($data)
    {
        $pattern = '~(?#Protocol)(?:(?:ht|f)tp(?:s?)\:\/\/)(?#Domain)(?:(?:[-\w]+\.)+(?#TopLevel Domains)(?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|[a-z]{2}))~i';
        $matches = array();
        $found = preg_match_all($pattern, $data, $matches);
        if ($found > 0 && isset($matches[0])) {
            $this->_log(sprintf("Found %u URL's", $found));
            return array_unique($matches[0]);
        }
        $this->_log(sprintf("No URL's found", $found));
        return array();
    }

    /**
     * Log to file/screen
     *
     * @param string $message
     */
    protected function _log($message)
    {
        $logLine = sprintf("%s: %s \n", date("Y-m-d H:i:s"),
        preg_replace("/[\r\n]/", " ", $message));
        echo $logLine;
        // @todo: Implement logging
        return true;
    }
}
