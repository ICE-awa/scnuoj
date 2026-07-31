<?php

use app\migrations\BaseMigration;

/**
 * Class m260531_090000_create_exam_login_guard
 */
class m260531_090000_create_exam_login_guard extends BaseMigration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%exam_login_guard}}', [
            'id' => $this->primaryKey(),
            'contest_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'active_session_id' => $this->string(128),
            'first_login_at' => $this->dateTime(),
            'first_login_ip' => $this->string(45),
            'first_login_user_agent' => $this->string(512),
            'last_login_at' => $this->dateTime(),
            'last_login_ip' => $this->string(45),
            'last_login_user_agent' => $this->string(512),
            'last_request_at' => $this->dateTime(),
            'last_request_ip' => $this->string(45),
            'last_request_user_agent' => $this->string(512),
            'approved_ip' => $this->string(45),
            'approved_login_count' => $this->integer()->notNull()->defaultValue(0),
            'used_login_count' => $this->integer()->notNull()->defaultValue(0),
            'status' => $this->smallInteger()->notNull()->defaultValue(0),
            'handled_by' => $this->integer(),
            'handled_at' => $this->dateTime(),
            'admin_note' => $this->string(255),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime(),
        ], $this->tableOptions);

        $this->createIndex('idx-exam-login-guard-contest-user', '{{%exam_login_guard}}', ['contest_id', 'user_id'], true);
        $this->createIndex('idx-exam-login-guard-contest-status', '{{%exam_login_guard}}', ['contest_id', 'status']);
        $this->createIndex('idx-exam-login-guard-user', '{{%exam_login_guard}}', 'user_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%exam_login_guard}}');
    }
}
