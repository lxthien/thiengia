<?php

namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Tên dịch vụ',
                'help' => 'Tên này cũng là một lựa chọn trong ô "Nhu cầu" của form báo giá trên trang chủ.',
            ])
            // HiddenType: giá trị do Media Picker ghi vào, xem _image_picker.html.twig.
            ->add('image', HiddenType::class, [
                'required' => false,
                'label' => 'Ảnh dịch vụ',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Mô tả ngắn',
                'attr' => ['rows' => 3],
                'help' => 'Hiển thị dưới tên dịch vụ trên trang chủ.',
            ])
            ->add('priceFrom', TextType::class, [
                'required' => false,
                'label' => 'Đơn giá từ',
                'help' => 'Ví dụ: từ 7,5 triệu/m². Để trống nếu chưa công bố giá.',
            ])
            ->add('slug', TextType::class, [
                'required' => false,
                'label' => 'Slug',
                'help' => 'Để dành cho trang dịch vụ riêng sau này. Chưa dùng tới, có thể để trống.',
            ])
            ->add('metaTitle', TextType::class, [
                'required' => false,
                'label' => 'Meta title',
            ])
            ->add('metaDescription', TextareaType::class, [
                'required' => false,
                'label' => 'Meta description',
                'attr' => ['rows' => 2],
            ])
            ->add('featuredOnHome', CheckboxType::class, [
                'required' => false,
                'label' => 'Đưa lên khối "Dịch vụ" ở trang chủ',
                'help' => 'Bỏ chọn thì dịch vụ vẫn nằm trong ô "Nhu cầu" của form báo giá, chỉ không có thẻ riêng trên trang chủ.',
            ])
            ->add('enable', CheckboxType::class, [
                'required' => false,
                'label' => 'Hiển thị dịch vụ này',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Service::class]);
    }
}
