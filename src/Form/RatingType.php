<?php

namespace App\Form;

use blackknight467\StarRatingBundle\Form\RatingType as BaseRatingType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * blackknight467/star-rating-bundle is unmaintained and its RatingType
 * still uses untyped method signatures, which triggers Symfony 6.4
 * "might add a native return type in the future" deprecations. This
 * subclass adds the explicit return types to silence them.
 */
class RatingType extends BaseRatingType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
    }

    public function getParent(): ?string
    {
        return parent::getParent();
    }
}
