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
                'label' => 'Source URL',
                'attr' => ['placeholder' => '/old-url or pattern']
            ])
            ->add('destinationUrl', TextType::class, [
                'label' => 'Destination URL',
                'attr' => ['placeholder' => '/new-url']
            ])
            ->add('matchType', ChoiceType::class, [
                'label' => 'Match Type',
                'choices' => [
                    'Exact' => 'exact',
                    'Wildcard' => 'wildcard',
                    'Regex' => 'regex'
                ]
            ])
            ->add('statusCode', ChoiceType::class, [
                'label' => 'Status Code',
                'choices' => [
                    '301 (Moved Permanently)' => 301,
                    '302 (Found / Temporary)' => 302,
                    '307 (Temporary Redirect)' => 307,
                    '308 (Permanent Redirect)' => 308,
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false
            ])
            ->add('orderNum', IntegerType::class, [
                'label' => 'Order',
                'data' => 0
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Note',
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
