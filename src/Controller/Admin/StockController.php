<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\StockRecord;
use App\Form\StockRecordType;
use App\Repository\StockRecordRepository;
use App\Service\StockRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/stock')]
final class StockController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StockRecordService $stockRecordService,
    ) {
    }

    #[Route(name: 'admin_stock_index', methods: ['GET'])]
    public function index(StockRecordRepository $stockRecordRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        return $this->render('admin/stock/index.html.twig', [
            'records' => $stockRecordRepository->findAllRecentFirst(),
        ]);
    }

    #[Route('/new', name: 'admin_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $record = new StockRecord();
        $user = $this->getUser();
        if ($user instanceof User) {
            $record->setCreatedBy($user);
        }
        $form = $this->createForm(StockRecordType::class, $record, ['product_locked' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->stockRecordService->persistNew($record);
                $this->entityManager->persist($record);
                $this->entityManager->flush();
                $this->addFlash('success', 'Stock record created and inventory updated.');

                return $this->redirectToRoute('admin_stock_index', [], Response::HTTP_SEE_OTHER);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Could not save stock record: '.$e->getMessage());
            }
        }

        return $this->render('admin/stock/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_stock_show', methods: ['GET'])]
    public function show(StockRecord $stockRecord): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        return $this->render('admin/stock/show.html.twig', [
            'record' => $stockRecord,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, StockRecord $stockRecord): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $originalDelta = $stockRecord->getQuantityDelta();
        $form = $this->createForm(StockRecordType::class, $stockRecord, ['product_locked' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $newDelta = $stockRecord->getQuantityDelta();
                $this->stockRecordService->replaceDelta($stockRecord, $originalDelta, $newDelta);
                $editor = $this->getUser();
                if ($editor instanceof User) {
                    $stockRecord->setUpdatedBy($editor);
                    $stockRecord->setUpdatedAt(new \DateTimeImmutable());
                }
                $this->entityManager->flush();
                $this->addFlash('success', 'Stock record updated and inventory adjusted.');

                return $this->redirectToRoute('admin_stock_index', [], Response::HTTP_SEE_OTHER);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Could not update stock record: '.$e->getMessage());
            }
        }

        return $this->render('admin/stock/edit.html.twig', [
            'record' => $stockRecord,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_stock_delete', methods: ['POST'])]
    public function delete(Request $request, StockRecord $stockRecord): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        if ($this->isCsrfTokenValid('delete'.$stockRecord->getId(), (string) $request->request->get('_token'))) {
            try {
                $this->stockRecordService->revert($stockRecord);
                $this->entityManager->remove($stockRecord);
                $this->entityManager->flush();
                $this->addFlash('success', 'Stock record deleted and inventory reverted.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Could not delete stock record: '.$e->getMessage());
            }
        } else {
            $this->addFlash('danger', 'Invalid security token.');
        }

        return $this->redirectToRoute('admin_stock_index', [], Response::HTTP_SEE_OTHER);
    }
}
