<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add optional public display name for post authors.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD publicDisplayName VARCHAR(255) DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Rollback disabled to protect public author names. Review a separate migration instead.');
    }
}
