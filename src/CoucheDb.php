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
date_default_timezone_set('Europe/Amsterdam');
require_once 'Zend/Http/Client.php';

class SitesBase
{
    const DATABASE = 'http://localhost:5984/spiderdb';

    const KEY_URL = 'url';
    const KEY_HOPS = 'hops';
    const KEY_PARENT = 'parent';
    const KEY_HTTPCODE = 'httpcode';
    const KEY_PARSETIME = 'parsetime';
    const KEY_NRLINKS = 'nrlinks';
    const KEY_NRFOUND = 'nrfound';
    const KEY_STATUS = 'status';
    const KEY_ADDDATE = 'adddate';

    const KEY_REV = '_rev';

    protected static $_db;

    public static function test()
    {
        $data = array('keys' => array('https://www.wacapps.net'));
        $http = new Zend_Http_Client(self::DATABASE . '/_design/spider/_view/byurl');
        $http->setRawData(json_encode($data));
        $http->setEncType('application/json');
        $data = json_decode($http->request(Zend_Http_Client::POST)->getBody());
        var_dump($data);

        return true;

        $data = array('foo' => 'bar1', '_rev' => '4-831e70e51908b81eedd7458b6684e9a7');

        $http = new Zend_Http_Client(self::DATABASE . '/mydoc5');
        //$http->setMethod(Zend_Http_Client::PUT);
        $http->setRawData(json_encode($data));
        $http->setEncType('multipart/form-data');
        $result = $http->request(Zend_Http_Client::PUT);
        var_dump($result); die;
        if (isset($result->ok, $result->id))
        {
             var_dump($result);
        }
    }

    public static function setup()
    {
        // @todo: Generate the nessecary views and db
        $stats = <<<STATISTICS
{
   "_id": "_design/statistics",
   "_rev": "2-1ff85df53f175d3dcd487e36c2f691d2",
   "language": "javascript",
   "views": {
       "nrprocessed": {
           "map": "function(doc) {\u000a  if (doc.status == 1) {\u000a    emit(\"processed\", 1);\u000a  }\u000a  else {\u000a    emit(\"not processed\", 1); \u000a  }\u000a}",
           "reduce": "function(keys, values) {\u000a   return sum(values);\u000a}"
       },
       "bydate": {
           "map": "function(doc) {\u000a  emit(doc.adddate, doc)\u000a}"
       }
   }
}
STATISTICS;

    $spider = <<<SPIDER
{
   "_id": "_design/spider",
   "_rev": "7-2da02e2324d5dd565e9a71b0ef03d131",
   "language": "javascript",
   "views": {
       "byurl": {
           "map": "function(doc) {\u000a     emit(doc.url, doc);\u000a}"
       },
       "unprocessed": {
           "map": "function(doc) {\u000a  if (doc.status == 0) {\u000a  \u0009emit(null, doc);\u000a  }\u000a}"
       }
   }
}
SPIDER;
    }

    public static function deleteAll()
    {
        // @todo: Implement this
        $http = new Zend_Http_Client(self::DATABASE . '/_design/spider/_view/byurl');
        $data = json_decode($http->request(Zend_Http_Client::GET)->getBody());
        if (isset($data->rows))
        {
            foreach($data->rows as $row)
            {
                $rowInfo = $row->value;
                $http = new Zend_Http_Client(self::DATABASE . '/' . $row->id . '?rev=' . $rowInfo->_rev);
                $resp = json_decode($http->request(Zend_Http_Client::DELETE)->getBody());
            }
            echo 'Deleted ' . $data->total_rows . ' rows' . PHP_EOL;
        }
    }

    public static function connect()
    {
        return true;
    }

    public static function alreadyExists($url)
    {
        $data = array('keys' => array($url));
        $http = new Zend_Http_Client(self::DATABASE . '/_design/spider/_view/byurl?limit=1');
        $http->setRawData(json_encode($data));
        $http->setEncType('application/json');
        $data = json_decode($http->request(Zend_Http_Client::POST)->getBody());
        $data = new ArrayObject($data->rows);

        if ($data->count() > 0) {
            $row = $data->getIterator()->current();

            if (!isset($row->value->nrfound)) {
                $row->value->nrfound = 0;
            }
            $row->value->nrfound++;

            $http = new Zend_Http_Client(self::DATABASE . '/' . $row->id);
            $http->setRawData(json_encode($row->value));
            $result = json_decode($http->request(Zend_Http_Client::PUT)->getBody());

            return $row->id;
        }
        return false;
    }

    public static function addSite($url, $hops, $parentId)
    {
        $exists = self::alreadyExists($url);
        if (false !== $exists)
        {
            return $exists;
        }
        $data = array(
            self::KEY_URL => $url,
            self::KEY_HOPS => $hops,
            self::KEY_PARENT => $parentId,
            self::KEY_STATUS => 0,
            self::KEY_ADDDATE => self::getDateFormat(time())
            );

        $http = new Zend_Http_Client(self::DATABASE . '/');
        $http->setRawData(json_encode($data));
        $http->setEncType('application/json');
        $result = json_decode($http->request(Zend_Http_Client::POST)->getBody());
        if (isset($result->ok, $result->id)) {
             return $result->id;
        }
    }

    public static function addMetadata($id, $httpCode, $parseTime, $nrlinks)
    {
        $http = new Zend_Http_Client(self::DATABASE . '/' . $id);
        $data = json_decode($http->request(Zend_Http_Client::GET)->getBody());

        $nrfound = 0;
        if (isset($data->nrfound) && $data->nrfound > 0)
        {
            $nrfound = $data->nrfound;
        }
        $data = array(
            self::KEY_REV => $data->_rev,
            self::KEY_URL => $data->url,
            self::KEY_HOPS => $data->hops,
            self::KEY_PARENT => $data->parent,
            self::KEY_ADDDATE => $data->adddate,
            self::KEY_NRFOUND => $nrfound,
            self::KEY_HTTPCODE => $httpCode,
            self::KEY_PARSETIME => $parseTime,
            self::KEY_NRLINKS => $nrlinks,
            self::KEY_STATUS => 1
            );
        $http = new Zend_Http_Client(self::DATABASE . '/' . $id);
        $http->setRawData(json_encode($data));
        $result = json_decode($http->request(Zend_Http_Client::PUT)->getBody());
        if (isset($result->ok, $result->rev))
        {
             return $result->rev;
        }
    }

    public static function getToBeParsed()
    {
        $http = new Zend_Http_Client(self::DATABASE . '/_design/spider/_view/unprocessed?limit=10');
        $data = json_decode($http->request(Zend_Http_Client::GET)->getBody());
        return $data->rows;
    }

    public static function generateId($url)
    {
        $url = str_replace(array('.', ':'), '_', $url);
        return preg_replace('~[^a-z0-9\-\_]~i', '', $url);
    }

    public static function getDateFormat($timestamp)
    {
        return array(
            (int) date('Y', $timestamp),
            (int) date('m', $timestamp),
            (int) date('d', $timestamp),
            (int) date('H', $timestamp),
            (int) date('i', $timestamp),
            (int) date('s', $timestamp)
            );
    }

    public static function getStats()
    {
        $http = new Zend_Http_Client(self::DATABASE . '/_design/statistics/_view/nrprocessed?group=true');
        $data = json_decode($http->request(Zend_Http_Client::GET)->getBody());
        $processed = $data->rows;

        $time = time();
        $query = 'startkey=' . json_encode(self::getDateFormat(strtotime('- 60 seconds', $time))) . '&endkey=' . json_encode(self::getDateFormat($time));
        $http = new Zend_Http_Client(self::DATABASE . '/_design/statistics/_view/bydate?' . $query);
        $data = json_decode($http->request(Zend_Http_Client::GET)->getBody());
        $itemsPerSecond = round(count($data->rows) / 60, 2);

        return array(
        	'not processed' => $processed[0]->value,
        	'processed' => $processed[1]->value,
        	'itemspersecond' => $itemsPerSecond
        );

        /**
         * Total nr sits
         * Total nr sites processed
         * Total nr sites to be processed
         * Sites found / second
         * Sites processed / second
         */

    }
}


//SitesBase::deleteAll();
//$http = new Zend_Http_Client('http://localhost:5984/_stats');
//$data = json_decode($http->request(Zend_Http_Client::GET)->getBody());
//var_dump($data);
var_dump(SitesBase::getStats());