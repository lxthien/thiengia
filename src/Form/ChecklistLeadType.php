<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form shortcode [form_tai_checklist] trong bài viết — HTML viết tay ở
 * v3/components/_checklist_form.html.twig (nhiều form/trang cần id riêng),
 * gửi AJAX tới checklist_lead. Tên field phải khớp với template đó.
 */
class ChecklistLeadType extends AbstractType
{
    public const NEED = 'Nhận check list';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [new Assert\NotBlank(message: 'Vui lòng nhập họ và tên.'), new Assert\Length(max: 100)],
            ])
            ->add('phone', TelType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Vui lòng nhập số điện thoại.'),
                    new Assert\Regex(pattern: CostLeadType::PHONE_PATTERN, message: 'Số điện thoại không hợp lệ, ví dụ 0901 234 567.'),
                ],
            ])
            ->add('agree', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [new Assert\IsTrue(message: 'Vui lòng đồng ý Chính sách bảo mật để nhận checklist.')],
            ])
            // Bài viết chứa shortcode và thứ tự của shortcode trong bài — để server
            // tự đọc lại danh sách file từ nội dung gốc.
            ->add('post', HiddenType::class, ['mapped' => false, 'constraints' => [new Assert\Positive()]])
            ->add('block', HiddenType::class, ['mapped' => false, 'constraints' => [new Assert\PositiveOrZero()]])
            // Bẫy bot: ô ẩn với người dùng, bot tự điền vào thì bị loại.
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [new Assert\Blank(message: 'Form không hợp lệ.')],
            ]);

        // Bỏ khoảng trắng, dấu chấm, gạch nối trước khi kiểm tra: "0901.234.567" vẫn hợp lệ
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            if (is_array($data) && isset($data['phone'])) {
                $data['phone'] = preg_replace('/[\s.\-()]/', '', (string) $data['phone']);
                $event->setData($data);
            }
        });

        // Contact::contents là NotBlank và được kiểm tra ngay trong handleRequest(),
        // nên phải điền trước bước validate; controller ghi nội dung đầy đủ sau.
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $contact = $event->getData();
            if ($contact instanceof Contact) {
                $contact->setTitle(self::NEED);
                $contact->setContents(self::NEED);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // CSRF giữ mặc định: template in token bằng csrf_token('checklist_lead')
        // (token id mặc định = tên form, xem ContactController::checklistLeadAction).
        $resolver->setDefaults(['data_class' => Contact::class]);
    }
}
