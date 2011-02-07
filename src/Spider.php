<?php
/**
 * TX-Spider
 *
 * @category   Spider
 * @package    Src
 * @copyright  Copyright (c) 2011 Tim de Pater
 * @license    http://www.gnu.org/licenses/gpl.txt GPLv3 License
 * @version    $Id:$
 */
require_once 'Zend/Http/Client.php';

class SiteSpider
{
    const LOGPATH = 'logs/spider.log';

    protected $_lastUrl;

    public function __construct()
    {
        SitesBase::connect();
    }

    public function run()
    {
        $urls = SitesBase::getToBeParsed();
        if (count($urls) == 0)
        {
            SitesBase::addSite(
    		    'http://www.enrise.com', 0, 0
    		);
    		$this->_log('No sites found, starting fresh');
        }

        $this->_parse();
    }

    protected function _parse()
	{
        $urls = SitesBase::getToBeParsed();

        // @todo: Use iterator
        foreach ($urls as $website)
        {
            $website = $website->value;
            $parseTime = microtime(true);
            $this->_log(sprintf('Parsing "%s" (id: %s, hop: %u)', $website->url, $website->_id, $website->hops));

    		$data = $this->_getData($website->url);
    		$urls = array();

    		if (null !== $data['content'])
    		{
    			$urls = $this->_getUrls($data['content']);
    			foreach ($urls as $url)
    			{
    			    SitesBase::addSite(
            		    $url, ($website->hops+1), $website->_id
            		);
    			}
    		}
    		SitesBase::addMetadata(
    		    $website->_id, $data['code'], round((microtime(true) - $parseTime), 4), count($urls)
    		);
        }
        $this->_log(sprintf('Memory usage: %.2fMB', memory_get_usage(true)/1024/1024));
        $this->_parse();
	}

    protected function _getData($url)
	{
	    $data = array('code' => 404, 'content' => null);

		$config = array (
			'useragent' => "TX-Spider",
			'maxredirects' => 5,
			'timeout' => 5
		);

		$client = new Zend_Http_Client($url, $config);
		try {
		    $request = $client->request();
		    $data['code'] = $request->getStatus();
		}
		catch (Zend_Http_Client_Adapter_Exception $e)
		{
		    $this->_log(sprintf('Failed opening "%s" (code: %u)', $url, $data['code']));
		    return $data;
		}
		if(!$request->isSuccessful())
		{
		    $this->_log(sprintf('Failed opening "%s" (code: %u)', $url, $data['code']));
		    return $data;
		}
        $content = $request->getBody();
		if($content && strlen($content) > 0)
		{
		    $data['content'] = $content;
		}
		else
		{
		    $this->_log(sprintf('Failed reading content from url "%s" (code: %u)', $url, $data['code']));
		}
		return $data;
	}

	protected function _getUrls($data)
	{
		$pattern = '~(?#Protocol)(?:(?:ht|f)tp(?:s?)\:\/\/)(?#Domain)(?:(?:[-\w]+\.)+(?#TopLevel Domains)(?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|[a-z]{2}))~i';
		$matches = array();
		$found = preg_match_all($pattern, $data, $matches);
		if($found > 0 && isset($matches[0]))
		{
			$this->_log(sprintf("Found %u URL's", $found));
			return array_unique($matches[0]);
		}
		$this->_log(sprintf("No URL's found", $found));
		return array();
	}

	protected function _log($message)
	{
		$logLine = sprintf("%s: %s \n", date("Y-m-d H:i:s"), preg_replace("/[\r\n]/", " ", $message));
		echo $logLine;
		return true;
		/*
		$filename = self::LOGPATH . "logger_" . date("Y-m-d") . ".log";
		if($filehandler = fopen($filename, 'a'))
		{
			fwrite($filehandler, $logLine);
			fclose($filehandler);
		}
		*/
	}
}
