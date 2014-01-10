<?php

error_reporting(E_ALL ^ E_STRICT ^ E_NOTICE);

include(__DIR__ . '/stdlibs/pixframework/Pix/Loader.php');
set_include_path(__DIR__ . '/stdlibs/pixframework/'
    . PATH_SEPARATOR . __DIR__ . '/models'
    . PATH_SEPARATOR . __DIR__ . '/Dropbox-master'
);

Pix_Loader::registerAutoLoad();

if (file_exists(__DIR__ . '/config.php')) {
    include(__DIR__ . '/config.php');
}
// TODO: 之後要搭配 geoip
date_default_timezone_set('Asia/Taipei');

if (!getenv('DATABASE_URL')) {
    die('need DATABASE_URL');
}
if (!preg_match('#mysql://([^:]*):([^@]*)@([^/]*)/(.*)#', strval(getenv('DATABASE_URL')), $matches)) {
    die('mysql only');
}

$db = new StdClass;
$db->host = $matches[3];
$db->username = $matches[1];
$db->password = $matches[2];
$db->dbname = $matches[4];
$config = new StdClass;
$config->master = $config->slave = $db;
Pix_Table::setDefaultDb(new Pix_Table_Db_Adapter_MysqlConf(array($config)));

if (!getenv('COMPANY_DATABASE_URL')) {
    die('need COMPANY_DATABASE_URL');
}
if (!preg_match('#mysql://([^:]*):([^@]*)@([^/]*)/(.*)#', strval(getenv('COMPANY_DATABASE_URL')), $matches)) {
    die('mysql only');
}

$db = new StdClass;
$db->host = $matches[3];
$db->username = $matches[1];
$db->password = $matches[2];
$db->dbname = $matches[4];
$config = new StdClass;
$config->master = $config->slave = $db;
$company_db = new Pix_Table_Db_Adapter_MysqlConf(array($config));
Unit::setDb($company_db);
UnitData::setDb($company_db);
ColumnGroup::setDb($company_db);
