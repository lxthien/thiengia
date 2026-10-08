<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename misspelled setting keys contens_* to content_*.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE settings SET setting_key = CONCAT('content_', SUBSTRING(setting_key, 9)) WHERE setting_key LIKE 'contens\\_%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE settings SET setting_key = CONCAT('contens_', SUBSTRING(setting_key, 9)) WHERE setting_key LIKE 'content\\_%'");
    }
}
