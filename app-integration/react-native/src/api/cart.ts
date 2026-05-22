import { apiRequest } from './client';
import type { Product } from './products';

export type CartItem = {
  id: number;
  quantity: number;
  unitPrice: number;
  lineTotal: number;
  product: Product;
};

export type Cart = {
  id: number;
  updatedAt: string;
  itemCount: number;
  subtotal: number;
  items: CartItem[];
};

export async function fetchCart(token: string): Promise<Cart> {
  return apiRequest<Cart>('/api/cart', { method: 'GET' }, token);
}

export async function addToCart(token: string, productId: number, quantity = 1): Promise<Cart> {
  return apiRequest<Cart>(
    '/api/cart/items',
    { method: 'POST', body: JSON.stringify({ productId, quantity }) },
    token,
  );
}

export async function updateCartItem(token: string, itemId: number, quantity: number): Promise<Cart> {
  return apiRequest<Cart>(
    `/api/customer/cart/items/${itemId}`,
    { method: 'PUT', body: JSON.stringify({ quantity }) },
    token,
  );
}

export async function removeCartItem(token: string, itemId: number): Promise<Cart> {
  return apiRequest<Cart>(`/api/cart/items/${itemId}`, { method: 'DELETE' }, token);
}

export async function clearCart(token: string): Promise<void> {
  await apiRequest('/api/customer/cart', { method: 'DELETE' }, token);
}
