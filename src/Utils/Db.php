<?php
namespace LTN\Utils;

class Db
{
    public $pdo;

    public function __construct(array $config)
    {
        $dbHost = $config['host'];
        $dbName = $config['name'];
        $dbUser = $config['user'];
        $dbPass = $config['pass'];

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8";
        $this->pdo = new \PDO($dsn, $dbUser, $dbPass, $options);
    }
}
