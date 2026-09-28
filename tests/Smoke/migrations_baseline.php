<?php
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/migrations/Version20260927022452.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MariaDb1043Platform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use DoctrineMigrations\Version20260927022452;
use Psr\Log\NullLogger;

// Explicit platform avoids opening a database connection; no SQL is executed.
$connection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'platform' => new MariaDb1043Platform(),
]);
$make = static fn () => new Version20260927022452($connection, new NullLogger());
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$migration = $make();
$migration->up(new Schema());
$sql = array_map(static fn ($query) => $query->getStatement(), $migration->getSql());
$check(count(array_filter($sql, static fn ($query) => str_starts_with($query, 'CREATE TABLE '))) === 28, 'Expected 28 baseline tables.');
$check(count($sql) === 45, 'Expected 28 CREATE TABLE and 17 foreign key statements.');
$check(!$migration->isTransactional(), 'MariaDB DDL must not claim transaction support.');
$schema = new Schema();
$schema->createTable('doctrine_migration_versions');
$make()->up($schema);
$schema->createTable('news');
$blocked = $make();
try {
    $blocked->up($schema);
    throw new RuntimeException('Existing schema was not blocked.');
} catch (AbortMigration $exception) {
    $check($blocked->getSql() === [], 'No SQL should be queued on an existing database.');
}
try {
    $make()->down(new Schema());
    throw new RuntimeException('Destructive baseline rollback was not blocked.');
} catch (IrreversibleMigration $exception) {
    // Expected: a baseline rollback must not erase CMS data.
}
echo "PASS: 28 tables, 17 foreign keys, existing-schema guard and irreversible baseline.\n";
