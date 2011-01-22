<?php
class SitesBase
{
    const DATABASE = 'db/websites.db';

    protected static $_db;

    public static function setup()
    {
        $query = "CREATE TABLE IF NOT EXISTS websites ( "
				."id integer not null primary key autoincrement, "
				."url varchar(255) not null unique, "
				."httpcode integer default null, "
				."hops integer not null, "
				."parsetime float default null, "
				."parent integer not null, "
				."status integer default 0, "
				."nrlinks integer default null, "
				."nrfound integer default null, "
				."adddate timestamp not null default (CURRENT_TIMESTAMP))";

        return self::$_db->exec($query);
    }

    public static function connect()
    {
        self::$_db = new PDO("sqlite:" . self::DATABASE);
        self::Setup();
    }

    public static function alreadyExists($url)
    {
        $query = self::$_db->prepare('SELECT id FROM websites WHERE url = ?');
		$query->execute(array($url));
		$data = $query->fetch();
		return isset($data['id']);
    }

    public static function getLast()
    {
        $query = self::$_db->prepare('SELECT url, hops, parent FROM websites ORDER BY adddate DESC LIMIT 1');
		$query->execute();
		return $query->fetch();
    }

    public static function addSite($url, $hops, $parentId)
    {
        $query = self::$_db->prepare(
        	"INSERT INTO websites (url, hops, parent, status) VALUES (?, ?, ?, ?)"
      	);
		$params = array($url, $hops, $parentId, 0);
		if ($query->execute($params))
		{
		    return self::$_db->lastInsertId();
		}
		return false;
    }

    public static function addMetadata($id, $httpCode, $parseTime, $nrlinks, $nrfound)
    {
        $query = self::$_db->prepare(
        	"UPDATE websites SET httpcode = ?, parsetime = ?, nrlinks = ?, nrfound = ?, status = ? WHERE id = ?"
      	);
		$params = array($httpCode, $parseTime, $nrlinks, $nrfound, 1, $id);
		if ($query->execute($params))
		{
		    return true;
		}
		return false;
    }

    public static function getToBeParsed()
    {
        $query = self::$_db->prepare('SELECT * FROM websites WHERE status = ? ORDER BY adddate ASC LIMIT 10');
        if ($query->execute(array(0)))
        {
		    return $query->fetchAll();
        }
        return array();
    }
}
