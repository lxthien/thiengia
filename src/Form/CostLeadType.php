<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Popup "nhận đơn giá chi tiết" hiện sau khi khách tính dự toán chi phí xây
 * dựng — gửi AJAX tới cost_calculator_lead (xem construction-cost.js).
 *
 * Nhu cầu (title) và mô tả (contents) được dựng sẵn từ kết quả dự toán ở
 * NewsController::costCalculatorAction và gửi kèm dạng hidden.
 */
class CostLeadType extends AbstractType
{
    // Di động Việt Nam: 0 hoặc +84, tiếp theo đầu số 3/5/7/8/9 và 8 chữ số
    // (giữ đồng bộ với kiểm tra phía trình duyệt trong construction-cost.js)
    public const PHONE_PATTERN = '/^(0|\+84)[35789]\d{8}$/';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Họ và tên *',
                'constraints' => [new Assert\NotBlank(message: 'Vui lòng nhập họ và tên.'), new Assert\Length(max: 100)],
                'attr' => ['placeholder' => 'Nguyễn Văn A', 'autocomplete' => 'name', 'maxlength' => 100],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Số điện thoại *',
                'constraints' => [
                    new Assert\NotBlank(message: 'Vui lòng nhập số điện thoại.'),
                    new Assert\Regex(pattern: self::PHONE_PATTERN, message: 'Số điện thoại không hợp lệ, ví dụ 0901 234 567.'),
                ],
                'attr' => [
                    'placeholder' => '09xx xxx xxx',
                    'autocomplete' => 'tel',
                    'inputmode' => 'tel',
                    'data-cost-lead-phone' => '',
                ],
            ])
            ->add('title', HiddenType::class, ['required' => false])
            ->add('contents', HiddenType::class)
            ->add('gclid', HiddenType::class, ['required' => false]);

        // Bỏ khoảng trắng, dấu chấm, gạch nối trước khi kiểm tra: "0901.234.567" vẫn hợp lệ
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            if (is_array($data) && isset($data['phone'])) {
                $data['phone'] = preg_replace('/[\s.\-()]/', '', (string) $data['phone']);
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Contact::class]);
    }
}
