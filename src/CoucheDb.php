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

    const KEY_REV = '_rev';

    protected static $_db;

    public static function setup()
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

    public static function deleteAll()
    {
        $http = new Zend_Http_Client('http://localhost:5984/' . self::DATABASE . '/_all_docs');
        $data = json_decode($http->request(Zend_Http_Client::GET)->getBody());
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

            return true;
        }
        return false;
    }

    public static function getLast()
    {
    }

    public static function addSite($url, $hops, $parentId)
    {
        $data = array(
            self::KEY_URL => $url,
            self::KEY_HOPS => $hops,
            self::KEY_PARENT => $parentId,
            self::KEY_STATUS => 0
            );

        $http = new Zend_Http_Client(self::DATABASE . '/');
        $http->setRawData(json_encode($data));
        $http->setEncType('application/json');
        $result = json_decode($http->request(Zend_Http_Client::POST)->getBody());
        if (isset($result->ok, $result->id)) {
             return $result->id;
        }
    }

    public static function addMetadata($id, $httpCode, $parseTime, $nrlinks, $nrfound)
    {
        $http = new Zend_Http_Client(self::DATABASE . '/' . $id);
        $data = json_decode($http->request(Zend_Http_Client::GET)->getBody());

        $data = array(
            self::KEY_REV => $data->_rev,
            self::KEY_URL => $data->url,
            self::KEY_HOPS => $data->hops,
            self::KEY_PARENT => $data->parent,
            self::KEY_HTTPCODE => $httpCode,
            self::KEY_PARSETIME => $parseTime,
            self::KEY_NRLINKS => $nrlinks,
            self::KEY_NRFOUND => $nrfound,
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
}

$test = SitesBase::alreadyExists('https://www.wacapps.net');
