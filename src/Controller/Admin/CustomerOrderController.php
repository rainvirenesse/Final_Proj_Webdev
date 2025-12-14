<?php

namespace App\Controller\Admin;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Form\CustomerOrderType;
use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/orders')]
final class CustomerOrderController extends AbstractController
{
    #[Route(name: 'admin_customer_order_index', methods: ['GET'])]
    public function index(CustomerOrderRepository $orderRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        return $this->render('admin/order/index.html.twig', [
            'orders' => $orderRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_customer_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository,
        ServiceRepository $serviceRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $order = new CustomerOrder();
        $order->setStatus(CustomerOrder::STATUS_PENDING); // Set default status
        $order->setPaymentStatus(CustomerOrder::PAYMENT_UNPAID); // Set default payment status
        $order->setCreatedBy($this->getUser());
        
        // Get active products and services
        $activeProducts = $productRepository->findActiveProducts();
        $activeServices = $serviceRepository->findActiveServices();
        
        $form = $this->createForm(CustomerOrderType::class, $order, [
            'is_new' => true,
            'active_products' => $activeProducts,
            'active_services' => $activeServices,
        ]);
        // Generate order number before form handling (for new orders)
        if (!$order->getId() && !$order->getOrderNumber()) {
            $orderNumber = $this->generateUniqueOrderNumber($entityManager);
            $order->setOrderNumber($orderNumber);
        }
        
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Get selected products and services before validation
            $selectedProducts = $form->get('selectedProducts')->getData();
            $selectedServices = $form->get('selectedServices')->getData();
            
            // Ensure order number is set (in case it wasn't set before)
            if (!$order->getOrderNumber()) {
                $orderNumber = $this->generateUniqueOrderNumber($entityManager);
                $order->setOrderNumber($orderNumber);
            }
            
            // Validate that at least one product or service is selected
            if (empty($selectedProducts) && empty($selectedServices)) {
                $this->addFlash('danger', 'Please select at least one product or service.');
            }
            
            if ($form->isValid()) {
                try {
                    // Validate that client is active
                    if ($order->getClient() && $order->getClient()->getStatus() !== User::STATUS_ACTIVE) {
                        throw new \InvalidArgumentException('Cannot create an order for a disabled client. Only active clients can receive orders.');
                    }
                    
                    // Ensure status and payment status are set for new orders
                    if (!$order->getStatus()) {
                        $order->setStatus(CustomerOrder::STATUS_PENDING);
                    }
                    if (!$order->getPaymentStatus()) {
                        $order->setPaymentStatus(CustomerOrder::PAYMENT_UNPAID);
                    }
                    
                    // Ensure order date is set
                    if ($order->getOrderedAt() === null) {
                        $order->setOrderedAt(new \DateTimeImmutable());
                    }
                    
                    // Ensure order date is not in the past
                    $now = new \DateTimeImmutable();
                    if ($order->getOrderedAt() < $now->setTime(0, 0, 0)) {
                        throw new \InvalidArgumentException('Order date cannot be before the current date.');
                    }
                    
                    // Create OrderItems for products
                    if (!empty($selectedProducts)) {
                        foreach ($selectedProducts as $product) {
                            if ($product === null) continue;
                            
                            $orderItem = new OrderItem();
                            $orderItem->setProduct($product);
                            $orderItem->setServiceName($product->getName());
                            $orderItem->setPrice($product->getPrice());
                            $orderItem->setQuantity(1);
                            $orderItem->setStatus('PENDING');
                            $orderItem->setCreatedAt(new \DateTime());
                            $orderItem->setUpdatedAt(new \DateTime());
                            $order->addOrderItem($orderItem);
                        }
                    }
                    
                    // Create OrderItems for services
                    if (!empty($selectedServices)) {
                        foreach ($selectedServices as $service) {
                            if ($service === null) continue;
                            
                            $orderItem = new OrderItem();
                            $orderItem->setService($service);
                            $orderItem->setServiceName($service->getName());
                            $orderItem->setPrice($service->getPrice());
                            $orderItem->setQuantity(1);
                            $orderItem->setRevisions($service->getRevisions() ?? 0);
                            $orderItem->setStatus('PENDING');
                            $orderItem->setCreatedAt(new \DateTime());
                            $orderItem->setUpdatedAt(new \DateTime());
                            $order->addOrderItem($orderItem);
                        }
                    }
                    
                    // Validate that we have at least one order item
                    if ($order->getOrderItems()->isEmpty()) {
                        throw new \InvalidArgumentException('At least one product or service must be selected.');
                    }
                    
                    // Calculate total from order items
                    $total = 0;
                    foreach ($order->getOrderItems() as $item) {
                        $itemPrice = (float)$item->getPrice();
                        $itemQuantity = $item->getQuantity() ?? 1;
                        $total += $itemPrice * $itemQuantity;
                    }
                    
                    // Validate total is greater than 0
                    if ($total <= 0) {
                        throw new \InvalidArgumentException('Order total must be greater than zero. Please select at least one product or service.');
                    }
                    
                    $order->setTotalPrice(number_format($total, 2, '.', ''));
                    
                    // Validate all required fields
                    if (!$order->getClient()) {
                        throw new \InvalidArgumentException('Client must be selected.');
                    }
                    
                    // Order number should already be set by now, but double-check
                    if (!$order->getOrderNumber()) {
                        $orderNumber = $this->generateUniqueOrderNumber($entityManager);
                        $order->setOrderNumber($orderNumber);
                    }
                    
                    if (!$order->getStatus()) {
                        throw new \InvalidArgumentException('Order status is required.');
                    }
                    
                    if (!$order->getPaymentStatus()) {
                        throw new \InvalidArgumentException('Payment status is required.');
                    }
                    
                    $entityManager->persist($order);
                    $entityManager->flush();
                    
                    $this->addFlash('success', 'Order created successfully!');
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
                    // Re-render form with errors
                    $productPrices = [];
                    foreach ($activeProducts as $product) {
                        $productPrices[$product->getId()] = (float)$product->getPrice();
                    }
                    $servicePrices = [];
                    foreach ($activeServices as $service) {
                        $servicePrices[$service->getId()] = (float)$service->getPrice();
                    }
                    return $this->render('admin/order/new.html.twig', [
                        'order' => $order,
                        'form' => $form,
                        'products' => $activeProducts,
                        'services' => $activeServices,
                        'productPrices' => $productPrices,
                        'servicePrices' => $servicePrices,
                    ]);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'An error occurred while creating the order: ' . $e->getMessage());
                    // Re-render form with errors
                    $productPrices = [];
                    foreach ($activeProducts as $product) {
                        $productPrices[$product->getId()] = (float)$product->getPrice();
                    }
                    $servicePrices = [];
                    foreach ($activeServices as $service) {
                        $servicePrices[$service->getId()] = (float)$service->getPrice();
                    }
                    return $this->render('admin/order/new.html.twig', [
                        'order' => $order,
                        'form' => $form,
                        'products' => $activeProducts,
                        'services' => $activeServices,
                        'productPrices' => $productPrices,
                        'servicePrices' => $servicePrices,
                    ]);
                }
                
                // Success - redirect after successful save
                return $this->redirectToRoute('admin_customer_order_index', [], Response::HTTP_SEE_OTHER);
            } else {
                // Form is submitted but invalid - show validation errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if (!empty($errors)) {
                    $this->addFlash('danger', 'error: ' . implode(', ', $errors));
                }
            }
        }

        // Create price map for JavaScript
        $productPrices = [];
        foreach ($activeProducts as $product) {
            $productPrices[$product->getId()] = (float)$product->getPrice();
        }
        
        $servicePrices = [];
        foreach ($activeServices as $service) {
            $servicePrices[$service->getId()] = (float)$service->getPrice();
        }

        return $this->render('admin/order/new.html.twig', [
            'order' => $order,
            'form' => $form,
            'products' => $activeProducts,
            'services' => $activeServices,
            'productPrices' => $productPrices,
            'servicePrices' => $servicePrices,
        ]);
    }

    #[Route('/{id}', name: 'admin_customer_order_show', methods: ['GET'])]
    public function show(CustomerOrder $order): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        return $this->render('admin/order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_customer_order_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        CustomerOrder $order, 
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository,
        ServiceRepository $serviceRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Check if order is completed - prevent editing
        if ($order->getStatus() === CustomerOrder::STATUS_COMPLETED) {
            $this->addFlash('warning', 'Cannot edit completed orders. Completed orders cannot be modified.');
            return $this->redirectToRoute('admin_customer_order_show', ['id' => $order->getId()], Response::HTTP_SEE_OTHER);
        }
        
        // Get active products and services
        $activeProducts = $productRepository->findActiveProducts();
        $activeServices = $serviceRepository->findActiveServices();
        
        // Pre-populate selected products and services from existing order items
        $existingProducts = [];
        $existingServices = [];
        foreach ($order->getOrderItems() as $item) {
            if ($item->getProduct() !== null) {
                $existingProducts[] = $item->getProduct();
            }
            if ($item->getService() !== null) {
                $existingServices[] = $item->getService();
            }
        }

        $form = $this->createForm(CustomerOrderType::class, $order, [
            'is_completed' => $order->getStatus() === CustomerOrder::STATUS_COMPLETED,
            'is_new' => false, // This is an edit, so show status and payment status fields
            'active_products' => $activeProducts,
            'active_services' => $activeServices,
        ]);
        
        // Pre-populate the form with existing products and services
        $form->get('selectedProducts')->setData($existingProducts);
        $form->get('selectedServices')->setData($existingServices);
        
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Get selected products and services before validation
            $selectedProducts = $form->get('selectedProducts')->getData();
            $selectedServices = $form->get('selectedServices')->getData();

            // Validate that at least one product or service is selected
            if (empty($selectedProducts) && empty($selectedServices)) {
                $this->addFlash('danger', 'Please select at least one product or service.');
                // Re-render form with errors
                $productPrices = [];
                foreach ($activeProducts as $product) {
                    $productPrices[$product->getId()] = (float)$product->getPrice();
                }
                $servicePrices = [];
                foreach ($activeServices as $service) {
                    $servicePrices[$service->getId()] = (float)$service->getPrice();
                }
                return $this->render('admin/order/edit.html.twig', [
                    'order' => $order,
                    'form' => $form,
                    'products' => $activeProducts,
                    'services' => $activeServices,
                    'productPrices' => $productPrices,
                    'servicePrices' => $servicePrices,
                ]);
            }

            if ($form->isValid()) {
                try {
                    // Validate that client is active
                    if ($order->getClient() && $order->getClient()->getStatus() !== User::STATUS_ACTIVE) {
                        throw new \InvalidArgumentException('Cannot assign an order to a disabled client. Only active clients can receive orders.');
                    }
                    
                    // Validate that order cannot be completed unless payment is PAID
                    if ($order->getStatus() === CustomerOrder::STATUS_COMPLETED && $order->getPaymentStatus() !== CustomerOrder::PAYMENT_PAID) {
                        throw new \InvalidArgumentException('Cannot complete an order that is not fully paid.');
                    }
                    
                    // Validate order date
                    if ($order->getOrderedAt() !== null) {
                        $now = new \DateTimeImmutable();
                        if ($order->getOrderedAt() < $now->setTime(0, 0, 0)) {
                            throw new \InvalidArgumentException('Order date cannot be before the current date.');
                        }
                    }
                    
                    // Validate completed date is not before order date
                    if ($order->getCompletedAt() !== null && $order->getOrderedAt() !== null) {
                        if ($order->getCompletedAt() < $order->getOrderedAt()) {
                            throw new \InvalidArgumentException('Completed date cannot be before the order date.');
                        }
                    }
                    
                    // Clear existing order items
                    foreach ($order->getOrderItems() as $item) {
                        $entityManager->remove($item);
                    }
                    $order->getOrderItems()->clear();
                    
                    // Create OrderItems for products
                    if (!empty($selectedProducts)) {
                        foreach ($selectedProducts as $product) {
                            if ($product === null) continue;
                            
                            $orderItem = new OrderItem();
                            $orderItem->setProduct($product);
                            $orderItem->setServiceName($product->getName());
                            $orderItem->setPrice($product->getPrice());
                            $orderItem->setQuantity(1);
                            $orderItem->setStatus('PENDING');
                            $orderItem->setCreatedAt(new \DateTime());
                            $orderItem->setUpdatedAt(new \DateTime());
                            $order->addOrderItem($orderItem);
                        }
                    }
                    
                    // Create OrderItems for services
                    if (!empty($selectedServices)) {
                        foreach ($selectedServices as $service) {
                            if ($service === null) continue;
                            
                            $orderItem = new OrderItem();
                            $orderItem->setService($service);
                            $orderItem->setServiceName($service->getName());
                            $orderItem->setPrice($service->getPrice());
                            $orderItem->setQuantity(1);
                            $orderItem->setRevisions($service->getRevisions() ?? 0);
                            $orderItem->setStatus('PENDING');
                            $orderItem->setCreatedAt(new \DateTime());
                            $orderItem->setUpdatedAt(new \DateTime());
                            $order->addOrderItem($orderItem);
                        }
                    }
                    
                    // Validate that we have at least one order item
                    if ($order->getOrderItems()->isEmpty()) {
                        throw new \InvalidArgumentException('At least one product or service must be selected.');
                    }
                    
                    // Calculate total from order items
                    $total = 0;
                    foreach ($order->getOrderItems() as $item) {
                        $itemPrice = (float)$item->getPrice();
                        $itemQuantity = $item->getQuantity() ?? 1;
                        $total += $itemPrice * $itemQuantity;
                    }
                    
                    // Validate total is greater than 0
                    if ($total <= 0) {
                        throw new \InvalidArgumentException('Order total must be greater than zero. Please select at least one product or service.');
                    }
                    
                    $order->setTotalPrice(number_format($total, 2, '.', ''));
                    
                    $entityManager->flush();
                    $this->addFlash('success', 'Order updated successfully!');
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
                    // Re-render form with errors
                    $productPrices = [];
                    foreach ($activeProducts as $product) {
                        $productPrices[$product->getId()] = (float)$product->getPrice();
                    }
                    $servicePrices = [];
                    foreach ($activeServices as $service) {
                        $servicePrices[$service->getId()] = (float)$service->getPrice();
                    }
                    return $this->render('admin/order/edit.html.twig', [
                        'order' => $order,
                        'form' => $form,
                        'products' => $activeProducts,
                        'services' => $activeServices,
                        'productPrices' => $productPrices,
                        'servicePrices' => $servicePrices,
                    ]);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'An error occurred while updating the order: ' . $e->getMessage());
                    // Re-render form with errors
                    $productPrices = [];
                    foreach ($activeProducts as $product) {
                        $productPrices[$product->getId()] = (float)$product->getPrice();
                    }
                    $servicePrices = [];
                    foreach ($activeServices as $service) {
                        $servicePrices[$service->getId()] = (float)$service->getPrice();
                    }
                    return $this->render('admin/order/edit.html.twig', [
                        'order' => $order,
                        'form' => $form,
                        'products' => $activeProducts,
                        'services' => $activeServices,
                        'productPrices' => $productPrices,
                        'servicePrices' => $servicePrices,
                    ]);
                }

                return $this->redirectToRoute('admin_customer_order_index', [], Response::HTTP_SEE_OTHER);
            } else {
                // Form is submitted but invalid - show validation errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if (!empty($errors)) {
                    $this->addFlash('danger', ' error: ' . implode(', ', $errors));
                }
            }
        }

        // Create price map for JavaScript
        $productPrices = [];
        foreach ($activeProducts as $product) {
            $productPrices[$product->getId()] = (float)$product->getPrice();
        }
        
        $servicePrices = [];
        foreach ($activeServices as $service) {
            $servicePrices[$service->getId()] = (float)$service->getPrice();
        }

        return $this->render('admin/order/edit.html.twig', [
            'order' => $order,
            'form' => $form,
            'products' => $activeProducts,
            'services' => $activeServices,
            'productPrices' => $productPrices,
            'servicePrices' => $servicePrices,
        ]);
    }

    #[Route('/{id}', name: 'admin_customer_order_delete', methods: ['POST'])]
    public function delete(Request $request, CustomerOrder $order, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->get('_token'))) {
            $entityManager->remove($order);
            $entityManager->flush();
            $this->addFlash('success', 'Order deleted successfully!');
        } else {
            $this->addFlash('danger', 'Invalid security token.');
        }

        return $this->redirectToRoute('admin_customer_order_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Generate a unique order number
     * Format: ORD-YYYYMMDD-XXXX (where XXXX is a random 4-digit number)
     */
    private function generateUniqueOrderNumber(EntityManagerInterface $entityManager): string
    {
        $orderRepository = $entityManager->getRepository(CustomerOrder::class);
        $maxAttempts = 100;
        $attempt = 0;
        
        do {
            // Generate order number: ORD-YYYYMMDD-XXXX
            $date = new \DateTime();
            $datePrefix = $date->format('Ymd');
            $randomSuffix = str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $orderNumber = 'ORD-' . $datePrefix . '-' . $randomSuffix;
            
            // Check if order number already exists
            $existingOrder = $orderRepository->findOneBy(['orderNumber' => $orderNumber]);
            $attempt++;
            
            if ($existingOrder === null) {
                return $orderNumber;
            }
        } while ($attempt < $maxAttempts);
        
        // If we couldn't generate a unique number after max attempts, use timestamp
        $timestamp = time();
        return 'ORD-' . $datePrefix . '-' . $timestamp;
    }
}
