import { apiRequest } from './client';

export type PaymentStatus = {
  orderId: number;
  orderNumber: string;
  paymentStatus: string;
  totalPrice: number;
  orderStatus: string;
  method?: string | null;
  reference?: string | null;
  paid: boolean;
};

export async function fetchPaymentStatus(token: string, orderId: number): Promise<PaymentStatus> {
  return apiRequest<PaymentStatus>(`/api/customer/orders/${orderId}/payment`, { method: 'GET' }, token);
}

export async function processPayment(
  token: string,
  orderId: number,
  method: string,
  reference?: string,
): Promise<PaymentStatus> {
  return apiRequest<PaymentStatus>(
    '/api/payments',
    { method: 'POST', body: JSON.stringify({ orderId, method, reference }) },
    token,
  );
}
