<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
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
    ) {
    }

    #[Route('lien-he/', name: 'contact')]
    public function indexAction(Request $request)
    {
        $contact = new Contact();

        $form = $this->createFormBuilder($contact)
            ->add('name', TextType::class, array('label' => 'Họ và tên *'))
            ->add('phone', TextType::class, array('label' => 'Số điện thoại *'))
            ->add('email', EmailType::class, array('label' => 'Email (không bắt buộc)', 'required' => false))
            ->add('contents', TextareaType::class, array(
                'label' => 'Nội dung yêu cầu tư vấn *',
                'attr' => array('rows' => '7')
            ))
            ->add('gclid', HiddenType::class, array('required' => false))
            ->add('send', SubmitType::class, array('label' => 'Gửi yêu cầu tư vấn', 'attr' => array('class' => 'btn btn-primary')))
            ->getForm();

        $form->handleRequest($request);

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

    #[Route('lien-he-ajax/', name: 'contact_ajax')]
    public function ajaxAction(Request $request)
    {
        $contact = new Contact();

        $form = $this->createFormBuilder($contact)
            ->add('name', TextType::class, array('label' => 'label.author'))
            ->add('email', EmailType::class, array('label' => 'label.author_email'))
            ->add('phone', TextType::class, array('label' => 'label.phone'))
            ->add('contents', TextareaType::class, array(
                'label' => 'label.content',
                'attr' => array('rows' => '7')
            ))
            ->add('gclid', HiddenType::class, array('required' => false))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
}
