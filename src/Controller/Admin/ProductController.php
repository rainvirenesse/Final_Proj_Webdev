<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\ProductImageFormHandler;
use App\Service\ProductImageStorage;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/products')]
final class ProductController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductImageFormHandler $productImageFormHandler,
        private ProductImageStorage $productImageStorage,
    ) {
    }
    #[Route(name: 'admin_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        return $this->render('admin/product/index.html.twig', [
            'products' => $productRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        $product = new Product();
        // Set default status to ACTIVE for new products
        $product->setStatus(Product::STATUS_ACTIVE);
        $product->setCreatedBy($this->getUser());
        $form = $this->createForm(ProductType::class, $product, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    // Validate required fields
                    if (!$product->getName()) {
                        throw new \InvalidArgumentException('Product name is required.');
                    }
                    
                    if ($product->getPrice() === null || (float)$product->getPrice() < 0) {
                        throw new \InvalidArgumentException('Product price must be zero or positive.');
                    }
                    
                    // Set default status to ACTIVE for new products
                    if (!$product->getStatus()) {
                        $product->setStatus(Product::STATUS_ACTIVE);
                    }
                    
                    // Ensure createdBy is set
                    if (!$product->getCreatedBy()) {
                        $product->setCreatedBy($this->getUser());
                    }

                    $this->productImageFormHandler->handle($form, $product);
                    
                    $this->entityManager->persist($product);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Product created successfully!');
                    return $this->redirectToRoute('admin_product_index', [], Response::HTTP_SEE_OTHER);
                } catch (FileException $e) {
                    $this->addFlash('danger', $e->getMessage());
                } catch (UniqueConstraintViolationException $e) {
                    if (str_contains($e->getMessage(), 'sku')) {
                        $this->addFlash('danger', 'A product with this SKU already exists. Please use a different SKU.');
                    } else {
                        $this->addFlash('danger', 'A product with this identifier already exists.');
                    }
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'An error occurred while creating the product: ' . $e->getMessage());
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

        return $this->render('admin/product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        return $this->render('admin/product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        $form = $this->createForm(ProductType::class, $product, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    // Validate required fields
                    if (!$product->getName()) {
                        throw new \InvalidArgumentException('Product name is required.');
                    }
                    
                    if ($product->getPrice() === null || (float)$product->getPrice() < 0) {
                        throw new \InvalidArgumentException('Product price must be zero or positive.');
                    }

                    $this->productImageFormHandler->handle($form, $product);
                    
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Product updated successfully!');
                    return $this->redirectToRoute('admin_product_index', [], Response::HTTP_SEE_OTHER);
                } catch (FileException $e) {
                    $this->addFlash('danger', $e->getMessage());
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash('danger', 'A product with this identifier already exists.');
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'An error occurred while updating the product: ' . $e->getMessage());
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

        return $this->render('admin/product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            try {
                // Check if product is used in any orders
                $orderItems = $this->entityManager->getRepository(\App\Entity\OrderItem::class)
                    ->findBy(['product' => $product]);
                
                if (!empty($orderItems)) {
                    $this->addFlash('danger', 'Cannot delete product. It is associated with existing orders.');
                    return $this->redirectToRoute('admin_product_index', [], Response::HTTP_SEE_OTHER);
                }

                $this->productImageStorage->removeIfManaged($product->getImage());
                
                $this->entityManager->remove($product);
                $this->entityManager->flush();
                $this->addFlash('success', 'Product deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'An error occurred while deleting the product: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('danger', 'Invalid security token.');
        }

        return $this->redirectToRoute('admin_product_index', [], Response::HTTP_SEE_OTHER);
    }
}

