<?php

namespace App\Form;

use App\Entity\Banner;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class BannerType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('bannercategory', null, [
                'label' => 'Nhóm banner',
                'placeholder' => 'Chọn nhóm banner',
                'constraints' => [new Assert\NotNull(message: 'Vui lòng chọn nhóm banner.')],
            ])
            ->add('name', TextType::class, [
                'label' => 'Tên banner',
                'constraints' => [new Assert\NotBlank(message: 'Vui lòng nhập tên banner.'), new Assert\Length(max: 255)],
            ])
            ->add('url', TextType::class, [
                'required' => false,
                'label' => 'Liên kết khi bấm vào banner',
                'constraints' => [new Assert\Length(max: 255), new Assert\Regex(pattern: '~^(?:/(?!/)|https?://|#)~i', message: 'Dùng đường dẫn nội bộ bắt đầu bằng /, # hoặc liên kết http(s).')],
            ])
            // Ảnh được chọn qua Media Library picker, ghi thẳng vào field này (xem admin/banner/_form.html.twig)
            ->add('urlImage', HiddenType::class)
            ->add('enable', CheckboxType::class, [
                'required' => false,
                'label' => 'Hiển thị',
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Banner::class,
        ]);
    }
}
