<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Contact;
use App\Entity\News;

class ContactController extends Controller
{
    /**
     * @Route("lien-he/", name="contact")
     */
    public function indexAction(Request $request, \Swift_Mailer $mailer)
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

            $em = $this->getDoctrine()->getManager();
            $em->persist($contact);
            $em->flush();
            
            if (null === $contact->getId()) {
                $this->addFlash(
                    'error',
                    $this->get('translator')->trans('contact.message.error')
                );

                return $this->render('contact/index.html.twig', [
                    'form' => $form->createView(),
                ]);
            } else {
                $this->addFlash(
                    'notice',
                    $this->get('translator')->trans('contact.message.success')
                );

                /*
                $message = (new \Swift_Message())
                        ->setSubject($this->get('translator')->trans('contact.email.title', ['%siteName%' => $this->get('settings_manager')->get('siteName')]))
                        ->setFrom(['hotro.xaydungminhduy@gmail.com' => $this->get('settings_manager')->get('siteName')])
                        ->setTo($this->get('settings_manager')->get('emailContact'))
                        ->setBody(
                            $this->renderView(
                                'Emails/contact.html.twig',
                                array(
                                    'name' => $form->get('name')->getData(),
                                    'phone' => $form->get('phone')->getData(),
                                    'email' => $form->get('email')->getData(),
                                    'body' => $form->get('contents')->getData(),
                                    'gclid' => $contact->getGclid()
                                )
                            ),
                            'text/html'
                        )
                    ;

                $mailer->send($message);
                */

                return $this->redirectToRoute('contact');
            }
        }

        $breadcrumbs = $this->get("white_october_breadcrumbs");
        $breadcrumbs->addItem("home", $this->generateUrl("homepage"));
        $breadcrumbs->addItem('contactus');

        $post = $this->getDoctrine()
            ->getRepository(News::class)
            ->findOneBy(
                array('url' => 'lien-he')
            );

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
            'post' => $post
        ]);
    }

    /**
     * @Route("lien-he-ajax/", name="contact_ajax")
     */
    public function ajaxAction(Request $request, \Swift_Mailer $mailer)
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
            $em = $this->getDoctrine()->getManager();
            $em->persist($contact);
            $em->flush();
            
            if (null === $contact->getId()) {
                return new JsonResponse(['success' => false, 'message' => $this->get('translator')->trans('contact.message.error')]);
            } else {
                /*
                $message = (new \Swift_Message())
                        ->setSubject($this->get('translator')->trans('contact.email.title', ['%siteName%' => $this->get('settings_manager')->get('siteName')]))
                        ->setFrom(['hotro.xaydungminhduy@gmail.com' => $this->get('settings_manager')->get('siteName')])
                        ->setTo($this->get('settings_manager')->get('emailContact'))
                        ->setBody(
                            $this->renderView(
                                'Emails/contact.html.twig',
                                array(
                                    'name' => $form->get('name')->getData(),
                                    'phone' => $form->get('phone')->getData(),
                                    'email' => $form->get('email')->getData(),
                                    'body' => $form->get('contents')->getData(),
                                    'gclid' => $contact->getGclid()
                                )
                            ),
                            'text/html'
                        );

                $mailer->send($message);
                */

                return new JsonResponse(['success' => true, 'message' => $this->get('translator')->trans('contact.message.success')]);
            }
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse(['success' => false, 'message' => implode(', ', $errors) ?: 'Form không hợp lệ. Vui lòng kiểm tra lại.']);
    }

    /**
     * @Route("page-builder-contact/", name="page_builder_contact_submit", methods={"POST"})
     */
    public function pageBuilderSubmitAction(Request $request, \Swift_Mailer $mailer)
    {
        $contact = new Contact();

        $form = $this->get('form.factory')->createNamedBuilder('page_builder_contact', FormType::class, $contact, [
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

        $em = $this->getDoctrine()->getManager();
        $em->persist($contact);
        $em->flush();

        if (null === $contact->getId()) {
            $this->addFlash('error', $this->get('translator')->trans('contact.message.error'));

            return new RedirectResponse($redirectUrl);
        }

        $message = (new \Swift_Message())
            ->setSubject($this->get('translator')->trans('contact.email.title', ['%siteName%' => $this->get('settings_manager')->get('siteName')]))
            ->setFrom(['hotro.xaydungminhduy@gmail.com' => $this->get('settings_manager')->get('siteName')])
            ->setTo($this->get('settings_manager')->get('emailContact'))
            ->setBody(
                $this->renderView(
                    'Emails/contact.html.twig',
                    array(
                        'name' => $form->get('name')->getData(),
                        'phone' => $form->get('phone')->getData(),
                        'email' => $form->get('email')->getData(),
                        'body' => $form->get('contents')->getData(),
                        'gclid' => $contact->getGclid()
                    )
                ),
                'text/html'
            );

        $mailer->send($message);

        $this->addFlash('notice', $this->get('translator')->trans('contact.message.success'));

        return new RedirectResponse($redirectUrl);
    }
}
