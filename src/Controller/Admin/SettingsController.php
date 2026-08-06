<?php

namespace App\Controller\Admin;

use App\Form\GlobalSettingsType;
use App\Service\SettingsManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/settings")
 * @IsGranted("ROLE_ADMIN")
 */
class SettingsController extends AbstractController
{
    /**
     * @Route("/global", name="admin_settings_global", methods={"GET", "POST"})
     */
    public function global(Request $request, SettingsManager $settingsManager): Response
    {
        $settings = $settingsManager->all();

        $form = $this->createForm(GlobalSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $settingsManager->setMany($data);

            $this->addFlash('success', 'Cài đặt đã được cập nhật thành công.');

            return $this->redirectToRoute('admin_settings_global');
        }

        return $this->render('admin/settings/global.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    /**
     * @Route("/construction-cost", name="admin_settings_construction_cost", methods={"GET", "POST"})
     */
    public function constructionCost(Request $request, SettingsManager $settingsManager): Response
    {
        $settings = $settingsManager->all();

        $form = $this->createForm(\App\Form\ConstructionCostSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $settingsManager->setMany($data);

            $this->addFlash('success', 'Cài đặt đơn giá xây dựng đã được cập nhật.');

            return $this->redirectToRoute('admin_settings_construction_cost');
        }

        return $this->render('admin/settings/construction_cost.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
