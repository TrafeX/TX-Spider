<?php
/**
 * TODO:
 * Add count per site, when we found it again increase the number
 * Create run script that forks the spider
 * Split in multiple files
 */

class SiteSpider
{
    const LOGPATH = 'logs/logs';

    protected $_db;
    protected $_lastUrl;

    public function __construct()
    {
        $this->_db = SitesBase::connect();
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

        foreach ($urls as $website)
        {
            $parseTime = microtime(true);
            $this->_log(sprintf('Parsing "%s" (%u)', $website['url'], $website['hops']));

    		$data = $this->_getData($website['url']);
    		$urls = array();

    		if (null !== $data['content'])
    		{
    			$urls = $this->_getUrls($data['content']);
    			foreach ($urls as $url)
    			{
    			    SitesBase::addSite(
            		    $url, ($website['hops']+1), $website['id']
            		);
    			}
    		}
    		SitesBase::addMetadata(
    		    $website['id'], $data['code'], round((microtime(true) - $parseTime), 4), count($urls), 0
    		);
        }
        $this->_parse();
	}

    protected function _getLast()
    {
        $last = SitesBase::getLast();
        if (!isset($last['url']))
        {
            $last['url'] = 'http://www.enrise.com';
            $last['hops'] = 0;
            $last['parent'] = 0;
        }
        $this->_lastUrl = $last['url'];
        return $last;
    }

    protected function _getData($url)
	{
	    $data = array('code' => 000, 'content' => null);

		$opts = array (
		  'http'=> array (
			'method' => "GET",
			'user_agent' => "TX-Spider",
			'max_redirects' => 5,
			'timeout' => 5,
			'header' => "Accept-language: en\r\n" .
						"Cookie: foo=bar\r\n"
		  	)
		);

		$context = stream_context_create($opts);
		$stream = fopen($url, "r", false, $context);
		if(!$stream)
		{
		    $data['code'] = 404;
		    $this->_log(sprintf('Failed opening "%s"', $url));
		    return $data;
		}
        $content = stream_get_contents($stream);
		if($content && strlen($content) > 0)
		{
		    fclose($stream);
		    $data['code'] = 200;
			$data['content'] = $content;
		}
		else
		{
		    $data['code'] = 503;
			$this->_log(sprintf('Failed reading content from url "%s"', $url));
		}
		@fclose($stream);
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
