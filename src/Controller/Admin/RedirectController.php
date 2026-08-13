<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Redirect;
use App\EventListener\RedirectSubscriber;
use App\Form\RedirectType;
use App\Service\ActivityLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin/redirect')]
#[IsGranted('ROLE_ADMIN')]
class RedirectController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
        private readonly CacheInterface $cache,
    ) {
    }

    #[Route('/', name: 'admin_redirect_index', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $queryBuilder = $this->em->getRepository(Redirect::class)->createQueryBuilder('r');

        if ($search = $request->query->get('search')) {
            $queryBuilder->andWhere('r.sourceUrl LIKE :search OR r.destinationUrl LIKE :search OR r.note LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($type = $request->query->get('type')) {
            $queryBuilder->andWhere('r.matchType = :type')
                ->setParameter('type', $type);
        }

        if ($isActive = $request->query->get('is_active')) {
            $queryBuilder->andWhere('r.isActive = :isActive')
                ->setParameter('isActive', $isActive === '1');
        }

        $queryBuilder->orderBy('r.orderNum', 'ASC')
            ->addOrderBy('r.id', 'DESC');

        // Without pagination bundle, we just fetch all or we can limit
        $redirects = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/redirect/index.html.twig', [
            'objects' => $redirects
        ]);
    }

    #[Route('/new', name: 'admin_redirect_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $redirect = new Redirect();
        $form = $this->createForm(RedirectType::class, $redirect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->detectChain($redirect->getSourceUrl(), $redirect->getDestinationUrl());

            $this->em->persist($redirect);
            $this->em->flush();
            $this->cache->delete(RedirectSubscriber::CACHE_KEY);

            $this->activityLogService->log(
                ActivityLog::ACTION_CREATE,
                'Redirect',
                $redirect->getId(),
                $redirect->getSourceUrl()
            );

            $this->addFlash('success', 'Redirect created successfully.');
            return $this->redirectToRoute('admin_redirect_index');
        }

        return $this->render('admin/redirect/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', requirements: ['id' => '\d+'], name: 'admin_redirect_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, Redirect $redirect)
    {
        $form = $this->createForm(RedirectType::class, $redirect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->detectChain($redirect->getSourceUrl(), $redirect->getDestinationUrl(), $redirect->getId());

            $diffDetails = $this->activityLogService->getEntityDiff($redirect);

            $this->em->flush();
            $this->cache->delete(RedirectSubscriber::CACHE_KEY);

            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                'Redirect',
                $redirect->getId(),
                $redirect->getSourceUrl(),
                $diffDetails
            );

            $this->addFlash('success', 'Redirect updated successfully.');
            return $this->redirectToRoute('admin_redirect_index');
        }

        return $this->render('admin/redirect/edit.html.twig', [
            'object' => $redirect,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_redirect_delete', methods: ['POST'])]
    public function deleteAction(Request $request, Redirect $redirect)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_redirect_index');
        }

        $id = $redirect->getId();
        $source = $redirect->getSourceUrl();

        $this->em->remove($redirect);
        $this->em->flush();
        $this->cache->delete(RedirectSubscriber::CACHE_KEY);

        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            'Redirect',
            $id,
            $source
        );

        $this->addFlash('success', 'Redirect deleted successfully.');
        return $this->redirectToRoute('admin_redirect_index');
    }

    #[Route('/{id}/toggle-status', name: 'admin_redirect_toggle_status', methods: ['POST'])]
    public function toggleStatusAction(Redirect $redirect)
    {
        $redirect->setIsActive(!$redirect->getIsActive());
        $this->em->flush();
        $this->cache->delete(RedirectSubscriber::CACHE_KEY);
        return $this->json(['status' => $redirect->getIsActive()]);
    }

    #[Route('/bulk-delete', name: 'admin_redirect_bulk_delete', methods: ['POST'])]
    public function bulkDeleteAction(Request $request)
    {
        $ids = $request->request->get('ids', []);
        if (is_array($ids) && count($ids) > 0) {
            $repository = $this->em->getRepository(Redirect::class);
            foreach ($ids as $id) {
                $redirect = $repository->find($id);
                if ($redirect) {
                    $this->em->remove($redirect);
                }
            }
            $this->em->flush();
            $this->cache->delete(RedirectSubscriber::CACHE_KEY);
            return $this->json(['message' => 'Deleted successfully.']);
        }
        return $this->json(['message' => 'No items selected.'], 400);
    }

    #[Route('/export-csv', name: 'admin_redirect_export_csv', methods: ['GET'])]
    public function exportCsvAction()
    {
        $redirects = $this->em->getRepository(Redirect::class)->findAll();
        $filename = "redirects_" . date('Y-m-d') . ".csv";

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Source URL', 'Destination URL', 'Match Type', 'Status Code', 'Active', 'Order', 'Note']);

        foreach ($redirects as $r) {
            fputcsv($handle, [
                $r->getSourceUrl(),
                $r->getDestinationUrl(),
                $r->getMatchType(),
                $r->getStatusCode(),
                $r->getIsActive() ? '1' : '0',
                $r->getOrderNum(),
                $r->getNote()
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $response = new Response($content);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', 'text/csv');

        return $response;
    }

    #[Route('/import-csv', name: 'admin_redirect_import_csv', methods: ['POST'])]
    public function importCsvAction(Request $request)
    {
        $file = $request->files->get('csv_file');
        if ($file && in_array($file->getClientOriginalExtension(), ['csv', 'txt'])) {
            $handle = fopen($file->getRealPath(), 'r');
            fgetcsv($handle); // skip header

            $count = 0;
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) >= 4) {
                    $redirect = new Redirect();
                    $redirect->setSourceUrl($data[0]);
                    $redirect->setDestinationUrl($data[1]);
                    $redirect->setMatchType($data[2]);
                    $redirect->setStatusCode((int)$data[3]);
                    $redirect->setIsActive(isset($data[4]) ? (bool)$data[4] : true);
                    $redirect->setOrderNum(isset($data[5]) ? (int)$data[5] : 0);
                    $redirect->setNote(isset($data[6]) ? $data[6] : null);

                    $this->em->persist($redirect);
                    $count++;
                }
            }
            fclose($handle);
            $this->em->flush();
            $this->cache->delete(RedirectSubscriber::CACHE_KEY);
            $this->addFlash('success', "Imported $count redirects successfully.");
        } else {
            $this->addFlash('error', "Invalid file format.");
        }

        return $this->redirectToRoute('admin_redirect_index');
    }

    private function detectChain($source, $destination, $excludeId = null)
    {
        if ($source === $destination) {
            $this->addFlash('warning', 'Warning: Source and Destination are identical. This might cause a redirect loop.');
            return;
        }

        $qb = $this->em->getRepository(Redirect::class)->createQueryBuilder('r')
            ->where('r.isActive = 1');

        if ($excludeId) {
            $qb->andWhere('r.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        // Direct chain: A -> B and B -> C
        $existsAsSource = (clone $qb)->andWhere('r.sourceUrl = :dest')->setParameter('dest', $destination)->getQuery()->getResult();
        if ($existsAsSource) {
            $this->addFlash('warning', "Chain Warning: Destination '$destination' is currently the Source of another active redirect.");
        }

        // Reverse chain: D -> A and A -> B
        $existsAsDestination = (clone $qb)->andWhere('r.destinationUrl = :src')->setParameter('src', $source)->getQuery()->getResult();
        if ($existsAsDestination) {
            $this->addFlash('warning', "Chain Warning: Source '$source' is currently the Destination of another active redirect.");
        }
    }
}
