<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Internal link report snapshot (internal_link_report).';
    }

    public function isTransactional(): bool
    {
        // MariaDB DDL implicitly commits.
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE internal_link_report (id INT AUTO_INCREMENT NOT NULL, generatedAt DATETIME NOT NULL, dataJson MEDIUMTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE internal_link_report');
    }
}
