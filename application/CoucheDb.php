<?php
/**
 * TrafexCrawler
 *
 * @category   Spider
 * @package    Src
 * @copyright  Copyright (c) 2011 Tim de Pater <code@trafex.nl>
 * @license    GPLv3 License http://www.gnu.org/licenses/gpl.txt
 * @version    $Id:$
 */

/**
 * The database for the sites covered by CouchDB
 *
 */
class SitesBase
{
    /**
     * CoucheDB host, port and database
     *
     * @var string
     */
    const DATABASE = 'http://debian-srv01.local:5984/spiderdb';

    /**#@+
     * Fields in the couchDB
     *
     * @var string
     */
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
    /**#@-*/

    /**
     * Setup the database once
     *
     * @return void
     */
    public static function setup()
    {
        // @todo: Call this once for setup
        $stats = <<<STATISTICS
{
   "_id": "_design/statistics",
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
/*
 *
{
  "_id" : "_design/test",
  "lists": {
    "bar": "function(head, req) { var row; while (row = getRow()) { send(row.value); } }"
  }
}
 *
 */
        // @todo: Batch?
        $http = new Zend_Http_Client(self::DATABASE . '/');
        $http->setRawData($stats);
        $http->setEncType('application/json');
        $data = Zend_Json::decode($http->request(Zend_Http_Client::POST)->getBody(), Zend_Json::TYPE_OBJECT);

        $http = new Zend_Http_Client(self::DATABASE . '/');
        $http->setRawData($spider);
        $http->setEncType('application/json');
        $data = Zend_Json::decode($http->request(Zend_Http_Client::POST)->getBody(), Zend_Json::TYPE_OBJECT);

        return true;

    }

    /**
     * Delete all records thus clearing the database
     *
     * @return void
     */
    public static function deleteAll()
    {
        $http = new Zend_Http_Client(self::DATABASE . '/_design/spider/_view/byurl');
        $data = Zend_Json::decode($http->request(Zend_Http_Client::GET)->getBody(), Zend_Json::TYPE_OBJECT);
        if (isset($data->rows)) {
            foreach($data->rows as $row) {
                $rowInfo = $row->value;
                $http = new Zend_Http_Client(self::DATABASE . '/' . $row->id . '?rev=' . $rowInfo->_rev);
                $resp = Zend_Json::decode($http->request(Zend_Http_Client::DELETE)->getBody(), Zend_Json::TYPE_OBJECT);
            }
            echo 'Deleted ' . $data->total_rows . ' rows' . PHP_EOL;
        }
    }

    /**
     * Check if url exists, if so; increase the nrfound
     *
     * @param string $url
     * @return bool|string
     */
    public static function alreadyExists($url)
    {
        $data = array('keys' => array($url));
        $http = new Zend_Http_Client(self::DATABASE . '/_design/spider/_view/byurl?limit=1');
        $http->setRawData(Zend_Json::encode($data));
        $http->setEncType('application/json');
        $data = Zend_Json::decode($http->request(Zend_Http_Client::POST)->getBody(), Zend_Json::TYPE_OBJECT);
        $data = new ArrayObject($data->rows);

        if ($data->count() > 0) {
            $row = $data->getIterator()->current();

            if (!isset($row->value->nrfound)) {
                $row->value->nrfound = 0;
            }
            $row->value->nrfound++;

            $http = new Zend_Http_Client(self::DATABASE . '/' . $row->id);
            $http->setRawData(Zend_Json::encode($row->value));
            $http->request(Zend_Http_Client::PUT);

            $id = $row->id;
            unset($http, $data, $row);
            return $id;
        }
        return false;
    }

    /**
     * Create site
     *
     * @param string $url
     * @param int $hops
     * @param string $parentId
     * @return string
     */
    public static function addSite($url, $hops, $parentId)
    {
        $exists = self::alreadyExists($url);
        if (false !== $exists) {
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
        $http->setRawData(Zend_Json::encode($data));
        $http->setEncType('application/json');
        $result = Zend_Json::decode($http->request(Zend_Http_Client::POST)->getBody(), Zend_Json::TYPE_OBJECT);
        if (isset($result->ok, $result->id)) {
             return $result->id;
        }
    }

    /**
     * Add metadata to existing site
     *
     * @param string $id
     * @param int $httpCode
     * @param float $parseTime
     * @param int $nrlinks
     * @return string
     */
    public static function addMetadata($id, $httpCode, $parseTime, $nrlinks)
    {
        $http = new Zend_Http_Client(self::DATABASE . '/' . $id);
        $data = Zend_Json::decode($http->request(Zend_Http_Client::GET)->getBody(), Zend_Json::TYPE_OBJECT);

        $nrfound = 0;
        if (isset($data->nrfound) && $data->nrfound > 0) {
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
        $http->setRawData(Zend_Json::encode($data));
        $result = Zend_Json::decode($http->request(Zend_Http_Client::PUT)->getBody(), Zend_Json::TYPE_OBJECT);
        if (isset($result->ok, $result->rev)) {
             return $result->rev;
        }
    }

    /**
     * Retrieve 10 unprocessed records
     *
     * @return array
     */
    public static function getToBeParsed()
    {
        $http = new Zend_Http_Client(self::DATABASE . '/_design/spider/_view/unprocessed?limit=10');
        $data = Zend_Json::decode($http->request(Zend_Http_Client::GET)->getBody(), Zend_Json::TYPE_OBJECT);

        return $data->rows;
    }

    /**
     * Create timestamp as a array
     *
     * @param int $timestamp
     * @return array
     */
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

    /**
     * Get statistics
     *
     * @return array
     */
    public static function getStats()
    {
        $http = new Zend_Http_Client(self::DATABASE . '/_design/statistics/_view/nrprocessed?group=true');
        $data = Zend_Json::decode($http->request(Zend_Http_Client::GET)->getBody(), Zend_Json::TYPE_OBJECT);
        $processed = $data->rows;

        $time = time();
        $query = 'startkey=' . Zend_Json::encode(self::getDateFormat(strtotime('- 60 seconds', $time))) . '&endkey=' . Zend_Json::encode(self::getDateFormat($time));
        $http = new Zend_Http_Client(self::DATABASE . '/_design/statistics/_view/bydate?' . $query);
        $data = Zend_Json::decode($http->request(Zend_Http_Client::GET)->getBody(), Zend_Json::TYPE_OBJECT);
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
	 * Views: ordered by nrfound
         */
    }
}
