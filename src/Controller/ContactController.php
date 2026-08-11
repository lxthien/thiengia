<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\Contact;
use App\Entity\News;
use App\Service\SettingsManager;
use WhiteOctober\BreadcrumbsBundle\Model\Breadcrumbs;

class ContactController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly SettingsManager $settingsManager,
        private readonly Breadcrumbs $breadcrumbs,
        private readonly FormFactoryInterface $formFactory,
        #[Autowire(service: 'limiter.public_form')]
        private readonly RateLimiterFactory $publicFormLimiter,
    ) {
    }

    #[Route('lien-he/', name: 'contact')]
    public function indexAction(Request $request)
    {
        $contact = new Contact();

        $formBuilder = $this->createFormBuilder($contact)
            ->add('name', TextType::class, array('label' => 'Họ và tên *'))
            ->add('phone', TextType::class, array('label' => 'Số điện thoại *'))
            ->add('title', ChoiceType::class, array(
                'label' => 'Nhu cầu của bạn',
                'required' => false,
                // Giữ đồng bộ với v3.menu.services trong config/packages/v3.yaml
                'choices' => array_combine($v3Services = [
                    'Xây nhà trọn gói',
                    'Xây nhà phần thô',
                    'Xây biệt thự',
                    'Sửa nhà trọn gói',
                    'Thiết kế kiến trúc',
                ], $v3Services),
            ))
            ->add('contents', TextareaType::class, array(
                'label' => 'Mô tả ngắn về dự án',
                'required' => false,
                'attr' => array('rows' => '4'),
            ))
            ->add('gclid', HiddenType::class, array('required' => false));

        $this->addContentsFallbackListener($formBuilder, 'Yêu cầu tư vấn từ trang liên hệ');

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$this->publicFormLimiter->create($request->getClientIp())->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Bạn gửi quá nhiều yêu cầu. Vui lòng thử lại sau ít phút.');

            return $this->render('contact/index.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contact);
            $this->em->flush();

            if (null === $contact->getId()) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('contact.message.error')
                );

                return $this->render('contact/index.html.twig', [
                    'form' => $form->createView(),
                ]);
            } else {
                $this->addFlash(
                    'notice',
                    $this->translator->trans('contact.message.success')
                );

                return $this->redirectToRoute('contact');
            }
        }

        $this->breadcrumbs->addItem("home", $this->generateUrl("homepage"));
        $this->breadcrumbs->addItem('contactus');

        $post = $this->em
            ->getRepository(News::class)
            ->findOneBy(
                array('url' => 'lien-he')
            );

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
            'post' => $post
        ]);
    }

    #[Route('lien-he-ajax/', name: 'contact_ajax', methods: ['POST'])]
    public function ajaxAction(Request $request)
    {
        if (!$this->publicFormLimiter->create($request->getClientIp())->consume(1)->isAccepted()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Bạn gửi quá nhiều yêu cầu. Vui lòng thử lại sau ít phút.',
            ], 429);
        }

        $contact = new Contact();
        // Contact::contents has #[Assert\NotBlank]; the quick-quote form (no
        // textarea) doesn't map this field, so pre-fill a placeholder here —
        // otherwise validation would fail before the real fallback below runs.
        $contact->setContents('Yêu cầu báo giá nhanh');

        $form = $this->createFormBuilder($contact)
            ->add('name', TextType::class, array('label' => 'label.author'))
            ->add('phone', TextType::class, array('label' => 'label.phone'))
            ->add('title', TextType::class, array('label' => 'label.title', 'required' => false))
            ->add('email', EmailType::class, array('label' => 'label.author_email', 'required' => false))
            ->add('gclid', HiddenType::class, array('required' => false))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($contact->getTitle()) {
                $contact->setContents('Yêu cầu báo giá nhanh — Nhu cầu: ' . $contact->getTitle());
            }

            $this->em->persist($contact);
            $this->em->flush();

            if (null === $contact->getId()) {
                return new JsonResponse(['success' => false, 'message' => $this->translator->trans('contact.message.error')]);
            } else {
                return new JsonResponse(['success' => true, 'message' => $this->translator->trans('contact.message.success')]);
            }
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse(['success' => false, 'message' => implode(', ', $errors) ?: 'Form không hợp lệ. Vui lòng kiểm tra lại.']);
    }

    #[Route('page-builder-contact/', name: 'page_builder_contact_submit', methods: ['POST'])]
    public function pageBuilderSubmitAction(Request $request, MailerInterface $mailer)
    {
        if (!$this->publicFormLimiter->create($request->getClientIp())->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Bạn gửi quá nhiều yêu cầu. Vui lòng thử lại sau ít phút.');

            return new RedirectResponse($request->headers->get('referer') ?: $this->generateUrl('contact'));
        }

        $contact = new Contact();

        $form = $this->formFactory->createNamedBuilder('page_builder_contact', FormType::class, $contact, [
                'action' => $this->generateUrl('page_builder_contact_submit'),
                'method' => 'POST',
            ])
            ->add('name', TextType::class, array('label' => 'Họ và tên *'))
            ->add('phone', TextType::class, array('label' => 'Số điện thoại *'))
            ->add('email', EmailType::class, array('label' => 'Email (không bắt buộc)', 'required' => false))
            ->add('contents', TextareaType::class, array(
                'label' => 'Nội dung yêu cầu tư vấn *',
                'attr' => array('rows' => '5')
            ))
            ->add('gclid', HiddenType::class, array('required' => false))
            ->add('send', SubmitType::class, array('label' => 'Gửi yêu cầu tư vấn', 'attr' => array('class' => 'btn btn-primary')))
            ->getForm();

        $form->handleRequest($request);

        $redirectUrl = $request->headers->get('referer') ?: $this->generateUrl('contact');

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Form liên hệ chưa hợp lệ. Vui lòng kiểm tra lại.');

            return new RedirectResponse($redirectUrl);
        }

        $this->em->persist($contact);
        $this->em->flush();

        if (null === $contact->getId()) {
            $this->addFlash('error', $this->translator->trans('contact.message.error'));

            return new RedirectResponse($redirectUrl);
        }

        $email = (new Email())
            ->subject($this->translator->trans('contact.email.title', ['%siteName%' => $this->settingsManager->get('siteName')]))
            ->from(new Address('hotro.xaydungminhduy@gmail.com', $this->settingsManager->get('siteName')))
            ->to($this->settingsManager->get('emailContact'))
            ->html(
                $this->renderView(
                    'Emails/contact.html.twig',
                    array(
                        'name' => $form->get('name')->getData(),
                        'phone' => $form->get('phone')->getData(),
                        'email' => $form->get('email')->getData(),
                        'body' => $form->get('contents')->getData(),
                        'gclid' => $contact->getGclid()
                    )
                )
            );

        $mailer->send($email);

        $this->addFlash('notice', $this->translator->trans('contact.message.success'));

        return new RedirectResponse($redirectUrl);
    }

    /**
     * Contact::contents has #[Assert\NotBlank], but some forms make the
     * description optional in the UI. Symfony validates as part of
     * handleRequest() itself (on FormEvents::POST_SUBMIT) rather than lazily
     * when isValid() is called, so filling in a fallback afterwards is too
     * late — it has to happen on FormEvents::SUBMIT, once data is mapped
     * onto the entity but before validation runs.
     */
    private function addContentsFallbackListener(FormBuilderInterface $formBuilder, string $fallback): void
    {
        $formBuilder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($fallback) {
            $contact = $event->getData();
            if ($contact instanceof Contact && empty($contact->getContents())) {
                $contact->setContents($fallback . ($contact->getTitle() ? ' — Nhu cầu: ' . $contact->getTitle() : ''));
            }
        });
    }
}
