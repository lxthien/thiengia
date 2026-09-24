<?php

namespace App\Command;

use App\Entity\HomepageSection;
use App\Enum\SectionType;
use App\Repository\HomepageSectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tạo các khối trang chủ còn thiếu theo đúng thứ tự đang chạy.
 *
 * Chạy được nhiều lần: khối đã có thì giữ nguyên thứ tự và nội dung admin
 * đã sửa. Ba ô chữ để trống có chủ ý — template sẽ dùng nội dung mặc định
 * của theme nên trang chủ không đổi gì sau khi seed.
 *
 *   php bin/console app:homepage:seed-sections
 */
class SeedHomepageSectionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HomepageSectionRepository $repository,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:homepage:seed-sections')
            ->setDescription('Tạo các khối trang chủ còn thiếu (idempotent).');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $existing = [];

        foreach ($this->repository->findAll() as $section) {
            $existing[$section->getType()->value] = $section;
        }

        $created = 0;
        $position = 0;

        foreach (SectionType::defaultOrder() as $type) {
            if (isset($existing[$type->value])) {
                ++$position;

                continue;
            }

            $section = new HomepageSection($type);
            $section->setPosition($position++);
            $this->em->persist($section);
            ++$created;
            $io->text(sprintf('+ %s (%s)', $type->label(), $type->value));
        }

        $this->em->flush();

        $io->success($created === 0
            ? 'Đã có đủ khối trang chủ, không tạo thêm gì.'
            : sprintf('Đã tạo %d khối trang chủ.', $created));

        return Command::SUCCESS;
    }
}
