<?php

namespace escape\escapedam\migrations;

use Craft;
use craft\db\Migration;

/**
 * Class m200921_000315_change_importedfiles_sourceelementid_fk_constraint
 * @package escape\escapedam\migrations
 */
class m200921_000315_change_importedfiles_sourceelementid_fk_constraint extends Migration
{

    /**
     * @inheritDoc
     */
    public function safeUp()
    {
        $this->alterColumn('{{%escapedam_importedfiles}}', 'fieldId', $this->integer()->null());
        $this->dropForeignKey($this->db->getForeignKeyName(), '{{%escapedam_importedfiles}}');
        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            '{{%escapedam_importedfiles}}',
            'fieldId',
            '{{%fields}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
        $this->dropForeignKey($this->db->getForeignKeyName(), '{{%escapedam_importedfiles}}');
        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            '{{%escapedam_importedfiles}}',
            'sourceElementId',
            '{{%elements}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    /**
     * @inheritDoc
     */
    public function safeDown()
    {
        echo "m200921_000315_change_importedfiles_sourceelementid_fk_constraint cannot be reverted.\n";
        return false;
    }

}
