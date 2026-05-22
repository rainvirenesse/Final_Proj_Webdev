import { apiRequest } from './client';

export type Product = {
  id: number;
  name: string;
  description?: string | null;
  price: number;
  status: string;
  stock: number;
  inventoryStock?: number;
  inStock?: boolean;
  category?: string | null;
  image?: string | null;
  imagePath?: string | null;
  imageUrl?: string | null;
};

export function resolveProductImageUri(product: Product, apiUrl: string): string | null {
  if (product.imageUrl) {
    return product.imageUrl;
  }
  const path = product.imagePath ?? product.image;
  if (!path) {
    return null;
  }
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }
  return `${apiUrl.replace(/\/$/, '')}/${path.replace(/^\//, '')}`;
}

export async function fetchProducts(): Promise<Product[]> {
  return apiRequest<Product[]>('/api/products');
}

export async function fetchProduct(id: number): Promise<Product> {
  return apiRequest<Product>(`/api/products/${id}`);
}
