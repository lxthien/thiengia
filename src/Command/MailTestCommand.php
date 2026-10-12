<?php

namespace App\Command;

use App\Entity\Contact;
use App\Service\ContactNotifier;
use App\Service\SettingsManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gửi thử một email thông báo liên hệ qua đúng cấu hình đang dùng (MAILER_DSN
 * + setting emailSender/emailContact) để kiểm tra SMTP mà không cần điền form:
 *
 *   php bin/console app:mail:test
 *
 * Không ghi gì vào database.
 */
class MailTestCommand extends Command
{
    public function __construct(
        private readonly ContactNotifier $notifier,
        private readonly SettingsManager $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:mail:test')
            ->setDescription('Gửi thử email thông báo liên hệ để kiểm tra cấu hình SMTP.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $recipient = (string) $this->settings->get('emailContact');
        $sender = (string) ($this->settings->get('emailSender') ?: $recipient);
        $transport = (string) ($_SERVER['MAILER_DSN'] ?? $_ENV['MAILER_DSN'] ?? '');

        $io->definitionList(
            ['Người gửi (From)' => $sender ?: '(chưa cấu hình)'],
            ['Người nhận (To)' => $recipient ?: '(chưa cấu hình)'],
            // Chỉ in phần giao thức và máy chủ, không in mật khẩu.
            ['Kênh gửi' => preg_replace('#//[^@/]*@#', '//***@', $transport) ?: '(trống)'],
        );

        if (str_starts_with($transport, 'null:')) {
            $io->warning('MAILER_DSN đang là null:// — lệnh sẽ báo thành công nhưng không có email nào thật sự được gửi.');
        }

        $contact = (new Contact())
            ->setName('Khách thử nghiệm')
            ->setPhone('0900 000 000')
            ->setEmail($recipient ?: null)
            ->setTitle('Xây nhà trọn gói')
            ->setContents("Đây là email thử từ lệnh app:mail:test.\nNếu bạn nhận được thì cấu hình gửi mail đã hoạt động.");

        try {
            $this->notifier->send($contact, 'Lệnh app:mail:test');
        } catch (\Throwable $e) {
            $io->error('Gửi thất bại: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Đã chuyển email cho máy chủ gửi thư. Kiểm tra hộp thư ' . $recipient . ' (cả mục Spam).');

        return Command::SUCCESS;
    }
}
