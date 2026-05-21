import { apiRequest } from './client';

export type CustomerProfile = {
  id: number;
  email: string;
  username: string;
  role: string;
  roles: string[];
  status: string;
  verified: boolean;
  createdAt: string;
};

export async function fetchProfile(token: string): Promise<CustomerProfile> {
  return apiRequest<CustomerProfile>('/api/customer/profile', { method: 'GET' }, token);
}

export async function updateProfile(token: string, username: string): Promise<CustomerProfile> {
  return apiRequest<CustomerProfile>(
    '/api/customer/profile',
    { method: 'PUT', body: JSON.stringify({ username }) },
    token,
  );
}
