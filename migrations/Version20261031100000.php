<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Public author page: slug, job title, expertise and profile links.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD authorSlug VARCHAR(190) DEFAULT NULL, ADD jobTitle VARCHAR(255) DEFAULT NULL, ADD knowsAbout VARCHAR(500) DEFAULT NULL, ADD sameAs LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6499483F92A ON `user` (authorSlug)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D6499483F92A ON `user`');
        $this->addSql('ALTER TABLE `user` DROP authorSlug, DROP jobTitle, DROP knowsAbout, DROP sameAs');
    }
}
