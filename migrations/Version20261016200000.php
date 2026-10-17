<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016200000 extends AbstractMigration
{
    public function getDescription(): string { return 'Record which public form each contact request came from.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD source VARCHAR(100) DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact DROP source');
    }
}
