import { apiRequest } from './client';

export type OrderItem = {
  id: number;
  name: string;
  quantity: number;
  price: number;
  lineTotal: number;
  status: string;
  productId?: number;
};

export type Order = {
  id: number;
  orderNumber: string;
  status: string;
  paymentStatus: string;
  totalPrice: number;
  orderedAt: string;
  notes?: string | null;
  completedAt?: string | null;
  itemCount?: number;
  items?: OrderItem[];
};

export async function fetchOrders(token: string): Promise<Order[]> {
  return apiRequest<Order[]>('/api/customer/orders', { method: 'GET' }, token);
}

export async function fetchOrder(token: string, id: number): Promise<Order> {
  return apiRequest<Order>(`/api/customer/orders/${id}`, { method: 'GET' }, token);
}

export async function createOrderFromCart(token: string, notes?: string): Promise<Order> {
  return apiRequest<Order>(
    '/api/customer/orders',
    { method: 'POST', body: JSON.stringify({ notes: notes ?? null }) },
    token,
  );
}

export async function cancelOrder(token: string, id: number): Promise<Order> {
  return apiRequest<Order>(`/api/customer/orders/${id}`, { method: 'DELETE' }, token);
}
