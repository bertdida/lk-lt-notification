<?php
namespace LTN\Utils;

use LTN\Utils\Db;

class Cron
{
    private $db;

    private $isHourly = false;

    public function __construct(Db $db, bool $isHourly = false)
    {
        $this->db = $db;
        $this->isHourly = $isHourly;
    }

    public function execute(): void
    {
        $stmt = $this->db->pdo->query('SELECT id FROM users WHERE status LIMIT 2');
        $commandFormat = 'php index.php %s %s';

        while ($row = $stmt->fetch()) {
            $command = sprintf($commandFormat, "--userid={$row['id']}", $this->isHourly ? '--ishourly' : '');
            $this->execInBackground($command);
        }
    }

    /**
     * Executes $command in the background (no cmd window) without
     * PHP waiting for it to finish, on both Windows and Unix.
     *
     * https://www.php.net/manual/en/function.exec.php#86329
     */
    private function execInBackground(string $command): void
    {
        if (substr(php_uname(), 0, 7) === 'Windows') {
            pclose(popen('start /B ' . $command, 'r'));
        } else {
            exec($command . ' > /dev/null &');
        }
    }
}