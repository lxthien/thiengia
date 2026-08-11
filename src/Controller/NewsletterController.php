<?php

namespace App\Controller;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/newsletter/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
    public function subscribeAction(Request $request, NewsletterSubscriberRepository $repository)
    {
        $subscriber = new NewsletterSubscriber();

        // Form công khai chỉ có 1 input <email> viết tay (không qua form_widget()
        // của Symfony) nên không có chỗ render _token — tắt CSRF cho riêng form này.
        // Hành động thấp rủi ro (đăng ký nhận tin, không đụng dữ liệu tài khoản), cùng
        // kiểu quyết định đã áp dụng cho UserController::changePasswordAction().
        $form = $this->createFormBuilder($subscriber, ['csrf_protection' => false])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Đã đăng ký rồi thì coi là thành công luôn (idempotent), tránh crash do
            // UNIQUE constraint và tránh phản hồi kiểu để lộ "email này có trong hệ
            // thống hay không" theo hướng khó chịu với người dùng thật.
            $existing = $repository->findOneByEmail($subscriber->getEmail());
            if ($existing) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Email này đã đăng ký nhận cẩm nang trước đó.',
                ]);
            }

            $subscriber->setIp($request->getClientIp());

            $this->em->persist($subscriber);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Đăng ký thành công! Cảm ơn bạn đã theo dõi.',
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse([
            'success' => false,
            'message' => implode(', ', $errors) ?: 'Email không hợp lệ. Vui lòng kiểm tra lại.',
        ]);
    }
}
