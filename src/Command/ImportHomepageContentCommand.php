<?php

namespace App\Command;

use App\Entity\HomepageSection;
use App\Entity\Project;
use App\Entity\Service;
use App\Enum\SectionType;
use App\Repository\HomepageSectionRepository;
use App\Repository\ProjectRepository;
use App\Repository\ServiceRepository;
use App\Service\Homepage\SectionIcons;
use App\Service\V3Config;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Chép nội dung mặc định của theme (config/packages/v3.yaml) vào DB để admin
 * sửa được trong giao diện:
 *   - phần chữ và danh sách lặp  -> HomepageSection::config
 *   - dịch vụ                    -> entity Service
 *   - công trình                 -> entity Project
 *
 * Chạy được nhiều lần: khóa config đã có thì giữ nguyên (trừ khi --force), còn
 * dịch vụ/công trình thì bỏ qua bản ghi đã tồn tại theo tên. Trang chủ không
 * đổi gì sau khi nhập — template vốn đã fallback về đúng những giá trị này.
 *
 *   php bin/console app:homepage:import-content
 */
class ImportHomepageContentCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HomepageSectionRepository $repository,
        private readonly ServiceRepository $serviceRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly V3Config $config,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:homepage:import-content')
            ->setDescription('Chép nội dung mặc định của theme vào phần quản lý khối trang chủ, dịch vụ và công trình.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ghi đè cả những khóa config admin đã sửa.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $imported = 0;
        $skipped = 0;

        foreach ($this->payload() as $typeValue => $values) {
            $type = SectionType::from($typeValue);
            $section = $this->repository->findOneByType($type);

            if (!$section instanceof HomepageSection) {
                $io->warning(sprintf('Chưa có khối "%s" — chạy app:homepage:seed-sections trước.', $typeValue));

                continue;
            }

            $config = $section->getConfig();
            $changed = false;

            foreach ($values as $key => $value) {
                if ($value === null || $value === [] || $value === '') {
                    continue;
                }

                if (!$force && array_key_exists($key, $config)) {
                    ++$skipped;

                    continue;
                }

                $config[$key] = $value;
                $changed = true;
                ++$imported;
            }

            if ($changed) {
                $section->setConfig($config);
                $io->text(sprintf('+ %s: %s', $type->label(), implode(', ', array_keys($values))));
            }
        }

        $services = $this->importServices($io);
        $projects = $this->importProjects($io);

        $this->em->flush();

        $io->success(sprintf(
            'Đã nhập %d mục nội dung, %d dịch vụ, %d công trình%s.',
            $imported,
            $services,
            $projects,
            $skipped > 0 ? sprintf(' (bỏ qua %d mục đã có nội dung, dùng --force để ghi đè)', $skipped) : '',
        ));

        return Command::SUCCESS;
    }

    private function importServices(SymfonyStyle $io): int
    {
        $existing = array_map(
            static fn (Service $service): string => mb_strtolower(trim((string) $service->getName())),
            $this->serviceRepository->findAll(),
        );
        $position = count($existing);
        $created = 0;

        foreach ($this->rows('services', ['name', 'image', 'desc']) as $row) {
            $name = (string) ($row['name'] ?? '');

            if ($name === '' || in_array(mb_strtolower(trim($name)), $existing, true)) {
                continue;
            }

            $service = new Service();
            $service->setName($name)
                ->setImage($row['image'] ?? null)
                // Mô tả trong theme đã gộp sẵn câu đơn giá, giữ nguyên để trang
                // chủ không đổi chữ nào; ô "Đơn giá từ" để trống cho admin điền.
                ->setDescription((string) ($row['desc'] ?? ''))
                ->setPosition($position++);
            $this->em->persist($service);
            $existing[] = mb_strtolower(trim($name));
            ++$created;
        }

        // Theme còn một danh sách nhu cầu riêng cho form báo giá (menu.services)
        // dài hơn danh sách thẻ dịch vụ. Những cái chỉ có ở đó được tạo với
        // featuredOnHome = false: vẫn chọn được khi xin báo giá, nhưng không
        // sinh thêm thẻ trên trang chủ.
        foreach ((array) $this->config->get('menu.services', []) as $name) {
            $name = is_string($name) ? trim($name) : '';

            if ($name === '' || in_array(mb_strtolower($name), $existing, true)) {
                continue;
            }

            $service = new Service();
            $service->setName($name)
                ->setDescription($name)
                ->setFeaturedOnHome(false)
                ->setPosition($position++);
            $this->em->persist($service);
            $existing[] = mb_strtolower($name);
            ++$created;
            $io->text(sprintf('+ %s (chỉ trong form báo giá)', $name));
        }

        if ($created > 0) {
            $io->text(sprintf('+ %d dịch vụ', $created));
        }

        return $created;
    }

    private function importProjects(SymfonyStyle $io): int
    {
        $existing = array_map(
            static fn (Project $project): string => mb_strtolower(trim((string) $project->getName())),
            $this->projectRepository->findAll(),
        );
        $position = count($existing);
        $created = 0;

        foreach ($this->rows('works', ['name', 'spec', 'image', 'path']) as $row) {
            $name = (string) ($row['name'] ?? '');
            $image = (string) ($row['image'] ?? '');

            if ($name === '' || $image === '' || in_array(mb_strtolower(trim($name)), $existing, true)) {
                continue;
            }

            $project = new Project();
            $project->setName($name)
                ->setSpec((string) ($row['spec'] ?? ''))
                ->setCoverImage($image)
                ->setLinkUrl($row['path'] ?? null)
                ->setPosition($position++);
            $this->em->persist($project);
            $existing[] = mb_strtolower(trim($name));
            ++$created;
        }

        if ($created > 0) {
            $io->text(sprintf('+ %d công trình', $created));
        }

        return $created;
    }

    /**
     * Nội dung theme, đã chuyển về đúng dạng mà form và template chờ đợi.
     *
     * @return array<string, array<string, mixed>>
     */
    private function payload(): array
    {
        return [
            'hero' => [
                'points' => array_values(array_filter(
                    (array) $this->config->get('heroPoints', []),
                    static fn ($point): bool => is_string($point) && trim($point) !== '',
                )),
            ],
            'figures' => [
                'items' => $this->rows('figures', ['value', 'unit', 'label']),
            ],
            'commitments' => [
                'items' => array_map(
                    // YAML lưu thẳng markup SVG; config chỉ lưu KEY an toàn.
                    static fn (array $row): array => [
                        'name' => $row['name'] ?? '',
                        'desc' => $row['desc'] ?? '',
                        'icon' => SectionIcons::keyForMarkup($row['icon'] ?? null),
                    ],
                    $this->rows('commitments', ['name', 'desc', 'icon']),
                ),
            ],
            'steps' => [
                'items' => $this->rows('steps', ['name', 'desc']),
            ],
            'about' => [
                'image' => (string) $this->config->get('about.image', ''),
                'imageAlt' => (string) $this->config->get('about.imageAlt', ''),
                'checks' => $this->rows('about.checks', ['title', 'text']),
            ],
            'cta' => [
                'image' => (string) $this->config->get('ctaImage', ''),
            ],
        ];
    }

    /**
     * @param list<string> $keys
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $path, array $keys): array
    {
        $rows = [];

        foreach ((array) $this->config->get($path, []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $clean = [];

            foreach ($keys as $key) {
                if (isset($row[$key]) && $row[$key] !== '') {
                    $clean[$key] = $row[$key];
                }
            }

            if ($clean !== []) {
                $rows[] = $clean;
            }
        }

        return $rows;
    }
}
