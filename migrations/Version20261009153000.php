<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009153000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add optional public author profile (avatar, biography, visibility) to users.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD avatar VARCHAR(255) DEFAULT NULL, ADD biography LONGTEXT DEFAULT NULL, ADD showAuthorInfo TINYINT(1) DEFAULT 0 NOT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Rollback disabled to protect saved author profiles. Review a separate migration instead.');
    }
}
