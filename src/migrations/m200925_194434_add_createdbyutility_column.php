<?php

namespace escape\escapedam\migrations;

use Craft;
use craft\db\Migration;

/**
 * Class m200925_194434_add_createdbyutility_column
 * @package escape\escapedam\migrations
 */
class m200925_194434_add_createdbyutility_column extends Migration
{

    /**
     * @inheritDoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%escapedam_importedfiles}}', 'createdByUtility', $this->boolean()->null());
    }

    /**
     * @inheritDoc
     */
    public function safeDown()
    {
        echo "m200925_194434_add_createdbyutility_column cannot be reverted.\n";
        return false;
    }

}
