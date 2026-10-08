<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Google Search Console daily stats (gsc_page_stat, gsc_query_stat).';
    }

    public function isTransactional(): bool
    {
        // MariaDB DDL implicitly commits.
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gsc_page_stat (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, path VARCHAR(500) NOT NULL, slug VARCHAR(191) NOT NULL, clicks INT DEFAULT 0 NOT NULL, impressions INT DEFAULT 0 NOT NULL, position DOUBLE PRECISION DEFAULT \'0\' NOT NULL, INDEX idx_gsc_page_slug_date (slug, date), INDEX idx_gsc_page_date (date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gsc_query_stat (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, path VARCHAR(500) NOT NULL, slug VARCHAR(191) NOT NULL, query VARCHAR(500) NOT NULL, clicks INT DEFAULT 0 NOT NULL, impressions INT DEFAULT 0 NOT NULL, position DOUBLE PRECISION DEFAULT \'0\' NOT NULL, INDEX idx_gsc_query_slug_date (slug, date), INDEX idx_gsc_query_date (date), INDEX idx_gsc_query_query (query(191)), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE gsc_query_stat');
        $this->addSql('DROP TABLE gsc_page_stat');
    }
}
