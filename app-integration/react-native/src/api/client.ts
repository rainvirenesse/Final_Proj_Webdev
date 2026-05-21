import { API_URL } from '../config/api';

export type ApiError = {
  status: number;
  error: string;
  code?: number;
  violations?: Record<string, string>;
};

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
  token?: string | null,
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const res = await fetch(`${API_URL}${path}`, { ...options, headers });
  const body = await res.json().catch(() => ({}));

  if (!res.ok) {
    const err: ApiError = {
      status: res.status,
      error: body.error ?? body.message ?? 'Request failed',
      code: body.code ?? res.status,
      violations: body.violations,
    };
    throw err;
  }

  return (body.data ?? body) as T;
}
