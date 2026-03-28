<?php

namespace App\Controller\Staff;

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

#[Route('/staff/orders')]
final class CustomerOrderController extends AbstractController
{
    #[Route(name: 'staff_customer_order_index', methods: ['GET'])]
    public function index(CustomerOrderRepository $orderRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        // Staff can view all orders
        $orders = $orderRepository->findAll();

        return $this->render('staff/order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/new', name: 'staff_customer_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository,
        ServiceRepository $serviceRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
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
                            $orderItem->setStatus('PENDING');
                            $orderItem->setCreatedAt(new \DateTime());
                            $orderItem->setUpdatedAt(new \DateTime());
                            $order->addOrderItem($orderItem);
                        }
                    }
                    
                    // Calculate total price from order items
                    $totalPrice = 0;
                    foreach ($order->getOrderItems() as $item) {
                        $totalPrice += (float)$item->getPrice() * $item->getQuantity();
                    }
                    $order->setTotalPrice($totalPrice);
                    
                    // Ensure createdBy is set
                    if (!$order->getCreatedBy()) {
                        $order->setCreatedBy($this->getUser());
                    }
                    
                    $entityManager->persist($order);
                    $entityManager->flush();
                    
                    $this->addFlash('success', 'Order created successfully!');
                    return $this->redirectToRoute('staff_customer_order_index', [], Response::HTTP_SEE_OTHER);
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
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
                    return $this->render('staff/order/new.html.twig', [
                        'order' => $order,
                        'form' => $form,
                        'products' => $activeProducts,
                        'services' => $activeServices,
                        'productPrices' => $productPrices,
                        'servicePrices' => $servicePrices,
                    ]);
                }
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

        return $this->render('staff/order/new.html.twig', [
            'order' => $order,
            'form' => $form,
            'products' => $activeProducts,
            'services' => $activeServices,
            'productPrices' => $productPrices,
            'servicePrices' => $servicePrices,
        ]);
    }

    #[Route('/{id}', name: 'staff_customer_order_show', methods: ['GET'])]
    public function show(CustomerOrder $order): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        // Staff can view all orders
        return $this->render('staff/order/show.html.twig', [
            'order' => $order,
        ]);
    }
     #[Route('/{id}', name: 'staff_customer_order_edit', methods: ['GET'])]
    public function edit(CustomerOrder $order): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        // Staff can view all orders
        return $this->render('staff/order/edit.html.twig', [
            'order' => $order,
        ]);
    }
    
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

