<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ngày cập nhật nội dung (chỉ đổi khi tích "Cập nhật nội dung đáng kể").
 * Bài cũ để NULL: updatedAt cũ đổi sau mọi lần lưu nên không phản ánh đúng
 * lần sửa nội dung thật — dateModified tạm lấy theo ngày đăng.
 */
final class Version20261102100000 extends AbstractMigration
{
    public function getDescription(): string { return 'News.contentUpdatedAt: date of the last significant content update.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE news ADD contentUpdatedAt DATETIME DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE news DROP contentUpdatedAt');
    }
}
