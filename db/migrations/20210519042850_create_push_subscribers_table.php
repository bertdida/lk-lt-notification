<?php
declare (strict_types = 1);

use Phinx\Migration\AbstractMigration;

final class CreatePushSubscribersTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('push_subscribers');
        $table->addColumn('lk_user_id', 'integer')
            ->addColumn('auth', 'string', ['limit' => 255])
            ->addColumn('p256dh', 'string', ['limit' => 255])
            ->addColumn('endpoint', 'string', ['limit' => 255])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
