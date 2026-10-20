<?php

namespace App\Command;

use App\Utils\ImageCopyright;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ghi metadata bản quyền (XMP) cho ảnh đã có sẵn trên site: ảnh gốc trong
 * public/uploads và ảnh dẫn xuất trong public/media/cache (thumbnail, .webp).
 * Ảnh mới tự được ghi lúc tạo (CKFinder plugin, MediaController, ConvertImages,
 * App\Imagine\CopyrightResolver) — command này để chạy bù, chạy lại bao nhiêu
 * lần cũng được (ảnh đã có đúng metadata thì bỏ qua).
 */
class EmbedImageCopyrightCommand extends Command
{
    private const DIRECTORIES = ['uploads', 'media/cache'];
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:media:embed-copyright')
            ->setDescription('Ghi metadata bản quyền (Creator/Copyright/Credit) vào ảnh trong uploads và media/cache')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Chỉ đếm, không ghi file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $counts = ['written' => 0, 'present' => 0, 'skipped' => 0];

        foreach ($this->images() as $path) {
            $data = @file_get_contents($path);
            if ($data === false) {
                $counts['skipped']++;
                continue;
            }
            if (ImageCopyright::hasCopyright($data)) {
                $counts['present']++;
                continue;
            }
            if (ImageCopyright::apply($data) === null) {
                // Không phải JPEG/PNG/WebP thật (sai đuôi) hoặc file hỏng — giữ nguyên.
                $counts['skipped']++;
                $io->writeln('Bỏ qua: ' . substr($path, strlen($this->publicDir) + 1), OutputInterface::VERBOSITY_VERBOSE);
                continue;
            }
            if ($dryRun || ImageCopyright::applyToFile($path)) {
                $counts['written']++;
            } else {
                $counts['skipped']++;
                $io->warning('Không ghi được: ' . $path);
            }
        }

        $io->success(sprintf(
            '%s %d ảnh, %d ảnh đã có sẵn, bỏ qua %d.',
            $dryRun ? '[dry-run] Sẽ ghi metadata cho' : 'Đã ghi metadata cho',
            $counts['written'],
            $counts['present'],
            $counts['skipped'],
        ));

        return Command::SUCCESS;
    }

    /**
     * @return \Generator<string>
     */
    private function images(): \Generator
    {
        foreach (self::DIRECTORIES as $directory) {
            $root = $this->publicDir . '/' . $directory;
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                // Bỏ thư mục/file ẩn (.ckfinder: thumbnail nội bộ của CKFinder).
                static fn (\SplFileInfo $file): bool => !str_starts_with($file->getFilename(), '.'),
            ));
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                    yield $file->getPathname();
                }
            }
        }
    }
}
