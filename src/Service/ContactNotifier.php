<?php

namespace App\Service;

use App\Entity\Contact;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Gửi email báo cho chủ site mỗi khi có yêu cầu mới từ form liên hệ, form
 * báo giá nhanh hoặc form trong page builder.
 *
 * Người gửi lấy từ setting "emailSender" (để trống thì dùng "emailContact"),
 * người nhận là "emailContact" — cả hai sửa ở /admin/settings/global. Với
 * Gmail SMTP, người gửi phải trùng tài khoản khai báo trong MAILER_DSN.
 */
final class ContactNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly SettingsManager $settings,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Dùng trong controller: yêu cầu đã được lưu vào DB, nên lỗi gửi mail
     * (SMTP sai, mất mạng...) chỉ được ghi log chứ không làm hỏng form.
     */
    public function notify(Contact $contact, string $source): bool
    {
        try {
            $this->send($contact, $source);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Không gửi được email thông báo liên hệ: {message}', [
                'message' => $e->getMessage(),
                'contactId' => $contact->getId(),
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Gửi và để nguyên exception — dùng cho lệnh app:mail:test để thấy lỗi SMTP.
     */
    public function send(Contact $contact, string $source): void
    {
        $recipient = trim((string) $this->settings->get('emailContact'));
        $sender = trim((string) $this->settings->get('emailSender')) ?: $recipient;

        if ($recipient === '') {
            throw new \RuntimeException('Chưa cấu hình "Email Liên hệ" trong Cài đặt chung nên không biết gửi thông báo cho ai.');
        }

        $siteName = (string) ($this->settings->get('siteName') ?: 'Website');

        $email = (new Email())
            ->from(new Address($sender, $siteName))
            ->to($recipient)
            ->subject(sprintf('[%s] Yêu cầu mới: %s — %s', $siteName, $contact->getName(), $contact->getPhone()))
            ->html($this->twig->render('Emails/contact.html.twig', [
                'contact' => $contact,
                'source' => $source,
                'adminUrl' => $this->urlGenerator->generate('admin_contact_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));

        // Bấm "Trả lời" trong hộp thư sẽ gửi thẳng cho khách nếu họ có để lại email.
        if ($contact->getEmail()) {
            $email->replyTo(new Address($contact->getEmail(), (string) $contact->getName()));
        }

        $this->mailer->send($email);
    }
}
