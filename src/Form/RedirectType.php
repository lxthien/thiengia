<?php

namespace App\Form;

use App\Entity\Redirect;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RedirectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('sourceUrl', TextType::class, [
                'label' => 'URL nguồn',
                'attr' => ['placeholder' => '/duong-dan-cu']
            ])
            ->add('destinationUrl', TextType::class, [
                'label' => 'URL đích',
                'attr' => ['placeholder' => '/new-url']
            ])
            ->add('matchType', ChoiceType::class, [
                'label' => 'Kiểu khớp',
                'choices' => [
                    'Chính xác' => 'exact',
                    'Wildcard (*)' => 'wildcard',
                    'Biểu thức chính quy (Regex)' => 'regex'
                ]
            ])
            ->add('statusCode', ChoiceType::class, [
                'label' => 'Mã HTTP',
                'choices' => [
                    '301 (Moved Permanently)' => 301,
                    '302 (Found / Temporary)' => 302,
                    '307 (Temporary Redirect)' => 307,
                    '308 (Permanent Redirect)' => 308,
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Bật chuyển hướng',
                'required' => false
            ])
            ->add('orderNum', IntegerType::class, [
                'label' => 'Thứ tự ưu tiên'
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Ghi chú',
                'required' => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Redirect::class,
        ]);
    }
}
