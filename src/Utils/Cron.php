<?php
namespace LTN\Utils;

use LTN\Utils\Db;

class Cron
{
    private $db;

    private $isHourly = false;

    private $testUserIds = [
        1, 3, 1436, 2701,
    ];

    public function __construct(Db $db, bool $isHourly = false)
    {
        $this->db = $db;
        $this->isHourly = $isHourly;
    }

    public function execute(int $userId = null): void
    {
        $userIds = !is_null($userId) ? [$userId] : $this->getUserIds();
        $commandFormat = 'php index.php %s %s';

        foreach ($userIds as $id) {
            $command = sprintf($commandFormat, "--userid={$id}", $this->isHourly ? '--ishourly' : '');
            $this->execInBackground($command);
        }
    }

    private function getUserIds(): array
    {
        if (empty($this->testUserIds)) {
            $stmt = $this->db->pdo->query('SELECT id FROM users WHERE status LIMIT 10');

        } else {
            $in = str_repeat('?,', count($this->testUserIds) - 1) . '?';
            $stmt = $this->db->pdo->prepare("SELECT id FROM users WHERE status AND id IN ({$in})");
            $stmt->execute($this->testUserIds);
        }

        $result = $stmt->fetchAll();
        return array_column($result, 'id');
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
