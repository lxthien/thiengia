<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sửa <img width/height> sai tỷ lệ so với file ảnh thật trong news.contents.
 *
 * CKEditor 5 đổi width/height thành style aspect-ratio, nên nội dung cũ mang kích
 * thước sai tỷ lệ (vd. 750×500 cho ảnh dọc 1536×2048) bị méo cả trong editor lẫn
 * ngoài site. Giữ nguyên chiều rộng, tính lại chiều cao theo ảnh thật. Ghi thẳng
 * bằng SQL để không kích hoạt HTMLPurifier/revision của News — chỉ đổi con số.
 */
class FixImageAspectRatioCommand extends Command
{
    private const TOLERANCE = 0.02;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:content:fix-image-aspect-ratio')
            ->setDescription('Sửa width/height của <img> trong bài viết cho đúng tỷ lệ ảnh thật')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ghi thay đổi vào DB (mặc định chỉ liệt kê)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $rows = [];
        $changedPosts = 0;

        foreach ($this->connection->iterateAssociative('SELECT id, contents FROM news') as $post) {
            $contents = (string) $post['contents'];
            $fixed = preg_replace_callback('/<img\b[^>]*>/i', function (array $match) use ($post, &$rows): string {
                $tag = $match[0];
                if (!preg_match('/\swidth="(\d+)"/i', $tag, $w)
                    || !preg_match('/\sheight="(\d+)"/i', $tag, $h)
                    || !preg_match('/\ssrc="([^"]+)"/i', $tag, $src)) {
                    return $tag;
                }

                $size = $this->naturalSize(html_entity_decode($src[1]));
                $width = (int) $w[1];
                $height = (int) $h[1];
                if (!$size || $width < 1 || $height < 1) {
                    return $tag;
                }

                $naturalRatio = $size[0] / $size[1];
                if (abs($width / $height - $naturalRatio) / $naturalRatio <= self::TOLERANCE) {
                    return $tag;
                }

                $newHeight = max(1, (int) round($width / $naturalRatio));
                $rows[] = [$post['id'], basename($src[1]), "{$width}×{$height}", "{$size[0]}×{$size[1]}", "{$width}×{$newHeight}"];

                $tag = preg_replace('/(\sheight=")\d+(")/i', '${1}' . $newHeight . '${2}', $tag, 1);

                return preg_replace('/aspect-ratio:\s*\d+\s*\/\s*\d+/i', "aspect-ratio:{$width}/{$newHeight}", $tag, 1);
            }, $contents);

            if ($fixed !== $contents) {
                $changedPosts++;
                if ($force) {
                    $this->connection->update('news', ['contents' => $fixed], ['id' => $post['id']]);
                }
            }
        }

        if (!$rows) {
            $io->success('Không có ảnh nào sai tỷ lệ.');

            return Command::SUCCESS;
        }

        $io->table(['Bài', 'Ảnh', 'Đang lưu', 'Ảnh thật', 'Sửa thành'], $rows);

        if ($force) {
            $io->success(sprintf('Đã sửa %d ảnh trong %d bài viết.', count($rows), $changedPosts));
        } else {
            $io->note(sprintf('%d ảnh trong %d bài viết sai tỷ lệ. Chạy lại với --force để ghi vào DB.', count($rows), $changedPosts));
        }

        return Command::SUCCESS;
    }

    /**
     * Chỉ đọc file ảnh nằm trong public/ của site (đường dẫn tương đối hoặc URL
     * cùng site) — ảnh ngoài thì bỏ qua, không tải về.
     */
    private function naturalSize(string $src): ?array
    {
        $path = parse_url($src, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path[0] !== '/' || str_contains($path, '..')) {
            return null;
        }

        $file = $this->publicDir . rawurldecode($path);
        $size = is_file($file) ? @getimagesize($file) : false;

        return $size && $size[0] > 0 && $size[1] > 0 ? [$size[0], $size[1]] : null;
    }
}
