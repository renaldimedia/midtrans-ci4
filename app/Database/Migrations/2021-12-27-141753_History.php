<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class History extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => [
                'type'           => 'INT',
                'constraint'     => 5,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'transaction_id'       => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'transaction_details' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'trasaction_status' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
            ],
            'created_at timestamp default current_timestamp',
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('transaction_id', true);
        $this->forge->createTable('history');
    }

    public function down()
    {
        $this->forge->dropTable('history');
    }
}
