<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';

// Regression: opening an existing rule must not reset its priority to zero.
$factory = Symfony\Component\Form\Forms::createFormFactoryBuilder()
    ->addType(new App\Form\RedirectType())->getFormFactory();
$redirect = (new App\Entity\Redirect())->setSourceUrl('/old')->setDestinationUrl('/new')->setOrderNum(27);
$form = $factory->create(App\Form\RedirectType::class, $redirect);
if ($form->get('orderNum')->getData() !== 27 || $redirect->getOrderNum() !== 27) {
    throw new RuntimeException('Existing priority was overwritten.');
}
$newForm = $factory->create(App\Form\RedirectType::class, new App\Entity\Redirect());
if ($newForm->get('orderNum')->getData() !== 0) {
    throw new RuntimeException('New rule default priority changed.');
}
echo "PASS: existing priority preserved; new rule defaults to zero. No database writes.\n";
