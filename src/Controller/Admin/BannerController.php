<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Banner;
use App\Form\BannerType;
use App\Service\ActivityLogService;

use App\Utils\Slugger;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller used to manage banner in the backend.
 * @Route("/admin/banner")
 * @Security("has_role('ROLE_ADMIN')")
 */

class BannerController extends Controller
{
    /**
     * Lists all the banner entities.
     *
     * @Route("/", name="admin_banner_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $banners = $em->getRepository(Banner::class)->findAll();

        return $this->render('admin/banner/index.html.twig', ['objects' => $banners]);
    }

    /**
     * @Route("/new", name="admin_banner_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request, Slugger $slugger)
    {
        $banner = new Banner();

        $form = $this->createForm(BannerType::class, $banner);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($banner);
            $em->flush();

            // Activity Log
            $this->get(ActivityLogService::class)->log(
                ActivityLog::ACTION_CREATE,
                ActivityLog::ENTITY_BANNER,
                $banner->getId(),
                $banner->getName()
            );

            $this->addFlash('success', 'action.created_successfully');

            return $this->redirectToRoute('admin_banner_index');
        }

        return $this->render('admin/banner/new.html.twig', [
            'object' => $banner,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}/edit", name="admin_banner_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Banner $banner, Slugger $slugger)
    {
        $form = $this->createForm(BannerType::class, $banner);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Capture changes before flush
            $diffDetails = $this->get(ActivityLogService::class)->getEntityDiff($banner);

            $this->getDoctrine()->getManager()->flush();

            // Activity Log
            $this->get(ActivityLogService::class)->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_BANNER,
                $banner->getId(),
                $banner->getName(),
                $diffDetails
            );

            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_banner_index');
        }

        return $this->render('admin/banner/edit.html.twig', [
            'object' => $banner,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a banner entity.
     *
     * @Route("/{id}/delete", name="admin_banner_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request, Banner $banner)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_banner_index');
        }

        $bannerName = $banner->getName();
        $bannerId = $banner->getId();

        $em = $this->getDoctrine()->getManager();
        $em->remove($banner);
        $em->flush();

        // Activity Log
        $this->get(ActivityLogService::class)->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_BANNER,
            $bannerId,
            $bannerName
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_banner_index');
    }
}
