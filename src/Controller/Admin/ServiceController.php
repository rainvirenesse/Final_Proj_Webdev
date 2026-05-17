<?php

namespace App\Controller\Admin;

use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\ServiceRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/services')]
final class ServiceController extends AbstractController
{
    #[Route(name: 'admin_service_index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        return $this->render('admin/service/index.html.twig', [
            'services' => $serviceRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_service_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        $service = new Service();
        // Ensure default values are set before form creation
        if ($service->getStatus() === null) {
            $service->setStatus(Service::STATUS_ACTIVE);
        }
        if ($service->getRevisions() === null) {
            $service->setRevisions(0);
        }
        $service->setCreatedBy($this->getUser());
        $form = $this->createForm(ServiceType::class, $service, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    // Validate required fields
                    if (!$service->getName()) {
                        throw new \InvalidArgumentException('Service name is required.');
                    }
                    
                    if ($service->getPrice() === null || (float)$service->getPrice() < 0) {
                        throw new \InvalidArgumentException('Service price must be zero or positive.');
                    }
                    
                    if (!$service->getDurationInHours() || $service->getDurationInHours() <= 0) {
                        throw new \InvalidArgumentException('Duration must be a positive number.');
                    }
                    
                    $entityManager->persist($service);
                    $entityManager->flush();
                    
                    $this->addFlash('success', 'Service created successfully!');
                    return $this->redirectToRoute('admin_service_index', [], Response::HTTP_SEE_OTHER);
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash('danger', 'A service with this name already exists. Please choose a different name.');
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'An error occurred while creating the service: ' . $e->getMessage());
                }
            } else {
                // Form validation failed
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if (!empty($errors)) {
                    $this->addFlash('danger', 'error: ' . implode(', ', $errors));
                }
            }
        }

        return $this->render('admin/service/new.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_service_show', methods: ['GET'])]
    public function show(Service $service): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        return $this->render('admin/service/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_service_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        $form = $this->createForm(ServiceType::class, $service, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    // Validate required fields
                    if (!$service->getName()) {
                        throw new \InvalidArgumentException('Service name is required.');
                    }
                    
                    if ($service->getPrice() === null || (float)$service->getPrice() < 0) {
                        throw new \InvalidArgumentException('Service price must be zero or positive.');
                    }
                    
                    if (!$service->getDurationInHours() || $service->getDurationInHours() <= 0) {
                        throw new \InvalidArgumentException('Duration must be a positive number.');
                    }
                    
                    $entityManager->flush();
                    
                    $this->addFlash('success', 'Service updated successfully!');
                    return $this->redirectToRoute('admin_service_index', [], Response::HTTP_SEE_OTHER);
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash('danger', 'A service with this name already exists. Please choose a different name.');
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'An error occurred while updating the service: ' . $e->getMessage());
                }
            } else {
                // Form validation failed
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if (!empty($errors)) {
                    $this->addFlash('danger', ' error: ' . implode(', ', $errors));
                }
            }
        }

        return $this->render('admin/service/edit.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_service_delete', methods: ['POST'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->request->get('_token'))) {
            try {
                // Check if service is used in any orders
                if (!$service->getOrderItems()->isEmpty()) {
                    $this->addFlash('danger', 'Cannot delete service. It is associated with existing orders.');
                    return $this->redirectToRoute('admin_service_index', [], Response::HTTP_SEE_OTHER);
                }
                
                $entityManager->remove($service);
                $entityManager->flush();
                $this->addFlash('success', 'Service deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'An error occurred while deleting the service: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('danger', 'Invalid security token.');
        }

        return $this->redirectToRoute('admin_service_index', [], Response::HTTP_SEE_OTHER);
    }
}
