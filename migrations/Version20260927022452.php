<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema baseline. Existing databases must adopt, not execute it.
 */
final class Version20260927022452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial CMS schema baseline (28 tables).';
    }

    public function isTransactional(): bool
    {
        // MariaDB DDL implicitly commits.
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach ($schema->getTables() as $table) {
            $this->abortIf(
                $table->getName() !== 'doctrine_migration_versions',
                'Baseline requires an empty database. See docs/database-migrations.md for existing databases.'
            );
        }
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDb1043Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDb1043Platform'."
        );

        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, username VARCHAR(255) DEFAULT NULL, action VARCHAR(20) NOT NULL, entityType VARCHAR(50) NOT NULL, entityId INT DEFAULT NULL, entityTitle VARCHAR(255) DEFAULT NULL, details LONGTEXT DEFAULT NULL, ipAddress VARCHAR(45) DEFAULT NULL, userAgent VARCHAR(255) DEFAULT NULL, createdAt DATETIME NOT NULL, INDEX idx_activity_log_user (user_id), INDEX idx_activity_log_action (action), INDEX idx_activity_log_entity (entityType), INDEX idx_activity_log_created (createdAt), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE banner (id INT AUTO_INCREMENT NOT NULL, bannercategory_id INT DEFAULT NULL, position INT DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, urlImage VARCHAR(255) NOT NULL, enable TINYINT(1) DEFAULT 1 NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, INDEX IDX_6F9DB8E7B378F775 (bannercategory_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE bannercategory (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, zone VARCHAR(50) DEFAULT \'hero\' NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, UNIQUE INDEX UNIQ_2659D17FF47645AE (url), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, comment_id INT DEFAULT NULL, news_id INT NOT NULL, content LONGTEXT NOT NULL, approved TINYINT(1) NOT NULL, email LONGTEXT DEFAULT NULL, phone LONGTEXT DEFAULT NULL, author LONGTEXT NOT NULL, ip LONGTEXT NOT NULL, gclid VARCHAR(255) DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, INDEX IDX_9474526CF8697D13 (comment_id), INDEX IDX_9474526CB5A459A0 (news_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, title VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, contents LONGTEXT NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, isRead TINYINT(1) DEFAULT 1 NOT NULL, gclid VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE content_decay_snapshot (id INT AUTO_INCREMENT NOT NULL, news_id INT DEFAULT NULL, decayScore INT NOT NULL, decayStatus VARCHAR(50) NOT NULL, ageDays INT NOT NULL, views INT NOT NULL, seoScore INT NOT NULL, seoStatus VARCHAR(50) NOT NULL, reasonsJson LONGTEXT NOT NULL, recommendationsJson LONGTEXT NOT NULL, isIndexable TINYINT(1) NOT NULL, generatedAt DATETIME NOT NULL, UNIQUE INDEX uniq_content_decay_snapshot_news (news_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE daily_stats (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, viewCount INT DEFAULT 0 NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, UNIQUE INDEX idx_daily_stats_date (date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gallery_album (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, position INT DEFAULT 0 NOT NULL, enable TINYINT(1) DEFAULT 1 NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gallery_image (id INT AUTO_INCREMENT NOT NULL, album_id INT NOT NULL, imageUrl VARCHAR(500) NOT NULL, caption VARCHAR(255) DEFAULT NULL, alt VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, createdAt DATETIME NOT NULL, INDEX IDX_21A0D47C1137ABCF (album_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE health_report (id INT AUTO_INCREMENT NOT NULL, generatedAt DATETIME NOT NULL, dataJson LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE homepage_section (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, label VARCHAR(255) DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, subtitle LONGTEXT DEFAULT NULL, config JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', position INT DEFAULT 0 NOT NULL, enable TINYINT(1) DEFAULT 1 NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, UNIQUE INDEX uniq_homepage_section_type (type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_asset (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(500) NOT NULL, alt_text VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1DB69EEDB548B0F (path), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, enable TINYINT(1) DEFAULT 1 NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, UNIQUE INDEX UNIQ_7D053A935E237E06 (name), INDEX idx_menu_enable (enable), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_item (id INT AUTO_INCREMENT NOT NULL, menu_id INT NOT NULL, parent_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, url VARCHAR(500) DEFAULT NULL, target_id INT DEFAULT NULL, target_type VARCHAR(50) DEFAULT NULL, css_class VARCHAR(255) DEFAULT NULL, target_attr VARCHAR(20) DEFAULT NULL, title_attr VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, enable TINYINT(1) DEFAULT 1 NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, INDEX IDX_D754D550CCD7E912 (menu_id), INDEX IDX_D754D550727ACA70 (parent_id), INDEX idx_menuitem_menu_enable (menu_id, enable), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE news (id INT AUTO_INCREMENT NOT NULL, author_id INT NOT NULL, parent_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, contents LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, images VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, publishedAt DATETIME DEFAULT NULL, scheduledAt DATETIME DEFAULT NULL, postType VARCHAR(255) NOT NULL, pageTitle VARCHAR(255) DEFAULT NULL, pageDescription LONGTEXT DEFAULT NULL, pageKeyword VARCHAR(255) DEFAULT NULL, breadcrumbTitle VARCHAR(255) DEFAULT NULL, relatedNews VARCHAR(255) DEFAULT NULL, contactHotline LONGTEXT DEFAULT NULL, viewCounts INT NOT NULL, ordering INT DEFAULT NULL, categoryPrimary INT NOT NULL, schemaMarkup LONGTEXT DEFAULT NULL, note LONGTEXT DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, metaIndex TINYINT(1) DEFAULT 1 NOT NULL, metaFollow TINYINT(1) DEFAULT 1 NOT NULL, template VARCHAR(255) DEFAULT NULL, pageBuilderEnabled TINYINT(1) DEFAULT 0 NOT NULL, pageBuilderData LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL, UNIQUE INDEX UNIQ_1DD39950F47645AE (url), INDEX IDX_1DD39950F675F31B (author_id), INDEX IDX_1DD39950727ACA70 (parent_id), INDEX idx_news_posttype_status (postType, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE news_newscategory (news_id INT NOT NULL, newscategory_id INT NOT NULL, INDEX IDX_C3AA8DEDB5A459A0 (news_id), INDEX IDX_C3AA8DED9D72BAE3 (newscategory_id), PRIMARY KEY(news_id, newscategory_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE news_tag (news_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_BE3ED8A1B5A459A0 (news_id), INDEX IDX_BE3ED8A1BAD26311 (tag_id), PRIMARY KEY(news_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE newscategory (id INT AUTO_INCREMENT NOT NULL, parentcat_id INT DEFAULT NULL, author_id INT NOT NULL, name VARCHAR(255) NOT NULL, titleLandingPage VARCHAR(255) DEFAULT NULL, url VARCHAR(255) NOT NULL, urlReplace VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, content LONGTEXT DEFAULT NULL, enable TINYINT(1) NOT NULL, showPostRelated TINYINT(1) NOT NULL, metaIndex TINYINT(1) DEFAULT 1 NOT NULL, metaFollow TINYINT(1) DEFAULT 1 NOT NULL, pageTitle VARCHAR(255) DEFAULT NULL, pageDescription LONGTEXT DEFAULT NULL, pageKeyword VARCHAR(255) DEFAULT NULL, sortBy VARCHAR(255) DEFAULT NULL, schemaMarkup LONGTEXT DEFAULT NULL, isPage TINYINT(1) NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, cardFormat VARCHAR(255) DEFAULT NULL, thumbnail VARCHAR(500) DEFAULT NULL, INDEX IDX_25E0E45AA0A4D17C (parentcat_id), INDEX IDX_25E0E45AF675F31B (author_id), INDEX idx_newscategory_url_enable (url, enable), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE newsletter_subscriber (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, ip VARCHAR(255) DEFAULT NULL, isRead TINYINT(1) DEFAULT 0 NOT NULL, createdAt DATETIME NOT NULL, UNIQUE INDEX UNIQ_401562C3E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, album_id INT DEFAULT NULL, name VARCHAR(150) NOT NULL, slug VARCHAR(180) DEFAULT NULL, spec VARCHAR(255) NOT NULL, coverImage VARCHAR(255) NOT NULL, linkUrl VARCHAR(255) DEFAULT NULL, area VARCHAR(60) DEFAULT NULL, floors VARCHAR(60) DEFAULT NULL, location VARCHAR(120) DEFAULT NULL, featuredOnHome TINYINT(1) DEFAULT 1 NOT NULL, metaTitle VARCHAR(255) DEFAULT NULL, metaDescription LONGTEXT DEFAULT NULL, position INT DEFAULT 0 NOT NULL, enable TINYINT(1) DEFAULT 1 NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, INDEX IDX_2FB3D0EE1137ABCF (album_id), UNIQUE INDEX uniq_project_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rating (id INT AUTO_INCREMENT NOT NULL, news_id INT NOT NULL, rating INT NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE redirects (id INT AUTO_INCREMENT NOT NULL, source_url VARCHAR(255) NOT NULL, destination_url VARCHAR(255) NOT NULL, match_type VARCHAR(20) NOT NULL, status_code INT NOT NULL, is_active TINYINT(1) NOT NULL, order_num INT NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(150) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, description LONGTEXT NOT NULL, priceFrom VARCHAR(120) DEFAULT NULL, metaTitle VARCHAR(255) DEFAULT NULL, metaDescription LONGTEXT DEFAULT NULL, featuredOnHome TINYINT(1) DEFAULT 1 NOT NULL, position INT DEFAULT 0 NOT NULL, enable TINYINT(1) DEFAULT 1 NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, UNIQUE INDEX uniq_service_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE settings (id INT AUTO_INCREMENT NOT NULL, setting_key VARCHAR(255) NOT NULL, setting_value LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_E545A0C55FA1E697 (setting_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, contents LONGTEXT DEFAULT NULL, pageTitle VARCHAR(255) DEFAULT NULL, pageDescription LONGTEXT DEFAULT NULL, pageKeyword VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_389B7835E237E06 (name), UNIQUE INDEX UNIQ_389B783F47645AE (url), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE testimonial (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, role VARCHAR(255) DEFAULT NULL, avatarUrl VARCHAR(255) NOT NULL, text LONGTEXT NOT NULL, rating INT DEFAULT 5 NOT NULL, position INT DEFAULT NULL, enable TINYINT(1) DEFAULT 1 NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', enabled TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, createdAt DATETIME DEFAULT NULL, adminNotificationRead TINYINT(1) DEFAULT 1 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE banner ADD CONSTRAINT FK_6F9DB8E7B378F775 FOREIGN KEY (bannercategory_id) REFERENCES bannercategory (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CB5A459A0 FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_decay_snapshot ADD CONSTRAINT FK_1DB3F7C5B5A459A0 FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gallery_image ADD CONSTRAINT FK_21A0D47C1137ABCF FOREIGN KEY (album_id) REFERENCES gallery_album (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE news ADD CONSTRAINT FK_1DD39950F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE news ADD CONSTRAINT FK_1DD39950727ACA70 FOREIGN KEY (parent_id) REFERENCES news (id)');
        $this->addSql('ALTER TABLE news_newscategory ADD CONSTRAINT FK_C3AA8DEDB5A459A0 FOREIGN KEY (news_id) REFERENCES news (id)');
        $this->addSql('ALTER TABLE news_newscategory ADD CONSTRAINT FK_C3AA8DED9D72BAE3 FOREIGN KEY (newscategory_id) REFERENCES newscategory (id)');
        $this->addSql('ALTER TABLE news_tag ADD CONSTRAINT FK_BE3ED8A1B5A459A0 FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE news_tag ADD CONSTRAINT FK_BE3ED8A1BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE newscategory ADD CONSTRAINT FK_25E0E45AA0A4D17C FOREIGN KEY (parentcat_id) REFERENCES newscategory (id)');
        $this->addSql('ALTER TABLE newscategory ADD CONSTRAINT FK_25E0E45AF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE1137ABCF FOREIGN KEY (album_id) REFERENCES gallery_album (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Baseline rollback would destroy the CMS database. Restore a verified backup instead.'
        );
    }
}
