/**
 * Parse token from POST /api/login response.
 * Supports both legacy `{ token }` and wrapped `{ data: { token } }` shapes.
 */
export function parseLoginToken(body: Record<string, unknown>): string | null {
  if (typeof body.token === 'string') {
    return body.token;
  }
  const data = body.data;
  if (data && typeof data === 'object' && 'token' in data && typeof (data as { token: unknown }).token === 'string') {
    return (data as { token: string }).token;
  }

  return null;
}
