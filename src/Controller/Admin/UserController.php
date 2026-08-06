<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Service\ActivityLogService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * User Management Controller
 */
#[Route('/admin/user')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Lists all users entities.
     */
    #[Route('/', name: 'admin_user_index', methods: ['GET'])]
    public function indexAction()
    {
        $userRepository = $this->em->getRepository(User::class);
        $userRepository->markAllRegistrationNotificationsAsRead();
        $users = $userRepository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);

        return $this->render('admin/user/index.html.twig', ['objects' => $users]);
    }

    /**
     * Display a form to create a new user
     */
    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $user = new User();
        $form = $this->createUserForm($user, true);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $encoded = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
            $user->setPassword($encoded);
            $user->setAdminNotificationRead(true);

            $this->em->persist($user);
            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_CREATE,
                ActivityLog::ENTITY_USER,
                $user->getId(),
                $user->getUserIdentifier(),
                'Vai trò: ' . implode(', ', $user->getRoles())
            );

            $this->addFlash('success', 'Người dùng đã được tạo thành công');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Display a form to edit an existing user
     */
    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, User $user)
    {
        $form = $this->createUserForm($user, false);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update password if provided
            if (!empty($user->getPlainPassword())) {
                $encoded = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
                $user->setPassword($encoded);
            }

            // Capture changes before flush
            $diffDetails = $this->activityLogService->getEntityDiff($user);

            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_USER,
                $user->getId(),
                $user->getUserIdentifier(),
                $diffDetails
            );

            $this->addFlash('success', 'Thông tin người dùng đã được cập nhật');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Delete a user
     */
    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteAction(Request $request, User $user)
    {
        // Prevent deleting current user
        if ($this->getUser()->getId() === $user->getId()) {
            $this->addFlash('error', 'Không thể xóa tài khoản của chính mình');
            return $this->redirectToRoute('admin_user_index');
        }

        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_user_index');
        }

        $username = $user->getUserIdentifier();
        $userId = $user->getId();

        $this->em->remove($user);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_USER,
            $userId,
            $username
        );

        $this->addFlash('success', 'Người dùng đã được xóa');
        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * Toggle user enabled/disabled status (Lock/Unlock account)
     */
    #[Route('/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatusAction(Request $request, User $user)
    {
        if (!$this->isCsrfTokenValid('toggle-status', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_user_index');
        }

        // Prevent disabling current user
        if ($this->getUser()->getId() === $user->getId()) {
            $this->addFlash('error', 'Không thể khoá tài khoản của chính mình');
            return $this->redirectToRoute('admin_user_index');
        }

        $user->setEnabled(!$user->isEnabled());
        $this->em->flush();

        $status = $user->isEnabled() ? 'mở khoá' : 'khoá';

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_TOGGLE,
            ActivityLog::ENTITY_USER,
            $user->getId(),
            $user->getUserIdentifier(),
            'Tài khoản đã được ' . $status
        );

        $this->addFlash('success', 'Tài khoản đã được ' . $status);
        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * Change user password
     */
    #[Route('/{id}/change-password', name: 'admin_user_change_password', methods: ['GET', 'POST'])]
    public function changePasswordAction(Request $request, User $user)
    {
        $form = $this->createFormBuilder(null, ['csrf_protection' => false])
            ->setAction($this->generateUrl('admin_user_change_password', ['id' => $user->getId()]))
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mật khẩu mới',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Vui lòng nhập mật khẩu']),
                    new Assert\Length([
                        'min' => 6,
                        'minMessage' => 'Mật khẩu tối thiểu 6 ký tự',
                    ])
                ]
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Xác nhận mật khẩu',
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Vui lòng xác nhận mật khẩu'])
                ]
            ])
            ->add('save', SubmitType::class, ['label' => 'Cập nhật mật khẩu'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            if ($plainPassword !== $confirmPassword) {
                $this->addFlash('error', 'Mật khẩu không khớp');
                return $this->redirectToRoute('admin_user_change_password', ['id' => $user->getId()]);
            }

            $encoded = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($encoded);
            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_USER,
                $user->getId(),
                $user->getUserIdentifier(),
                'Đổi mật khẩu'
            );

            $this->addFlash('success', 'Mật khẩu đã được cập nhật');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/change_password.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Create user form
     *
     * @param User $user
     * @param bool $isNew Whether this is a new user or edit
     * @return \Symfony\Component\Form\FormInterface
     */
    private function createUserForm(User $user, $isNew = false)
    {
        $builder = $this->createFormBuilder($user)
            ->add('username', TextType::class, [
                'label' => 'Tên đăng nhập',
                'attr' => ['readonly' => !$isNew]
            ])
            ->add('name', TextType::class, [
                'label' => 'Tên hiển thị',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Vui lòng nhập tên']),
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Vui lòng nhập email']),
                    new Assert\Email(['message' => 'Email không hợp lệ']),
                ]
            ]);

        // Password field only for new users
        if ($isNew) {
            $builder->add('plainPassword', PasswordType::class, [
                'label' => 'Mật khẩu',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Vui lòng nhập mật khẩu']),
                    new Assert\Length([
                        'min' => 6,
                        'minMessage' => 'Mật khẩu tối thiểu 6 ký tự',
                    ])
                ]
            ]);
        }

        $builder
            ->add('roles', ChoiceType::class, [
                'label' => 'Quyền',
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Editor' => 'ROLE_EDITOR',
                    'Author' => 'ROLE_AUTHOR',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Kích hoạt',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => $isNew ? 'Tạo người dùng' : 'Cập nhật',
            ]);

        return $builder->getForm();
    }
}
