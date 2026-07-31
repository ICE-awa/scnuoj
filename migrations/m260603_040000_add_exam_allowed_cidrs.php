<?php

use yii\db\Migration;

/**
 * Class m260603_040000_add_exam_allowed_cidrs
 */
class m260603_040000_add_exam_allowed_cidrs extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert('{{%setting}}', [
            'key' => 'examAllowedCidrs',
            'value' => "10.191.0.0/16",
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('{{%setting}}', ['key' => 'examAllowedCidrs']);
    }
}
