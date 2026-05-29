/**
 * CREDO realtime WebSocket hub.
 *
 * - WebSocket clients connect with ?token=<subscribe-token> (HMAC from Symfony).
 * - Symfony POSTs to /broadcast with X-Broadcast-Key to fan out events by channel.
 */
import http from 'node:http';
import crypto from 'node:crypto';
import { WebSocketServer } from 'ws';

const PORT = Number(process.env.PORT || process.env.REALTIME_WS_PORT || 8090);
const BROADCAST_KEY = process.env.REALTIME_WS_BROADCAST_KEY || 'credo-dev-broadcast-key';

/** @type {Map<import('ws').WebSocket, { channels: Set<string> }>} */
const clients = new Map();

function verifySubscribeToken(token) {
  if (!token || typeof token !== 'string') {
    return null;
  }
  const parts = token.split('.');
  if (parts.length !== 2) {
    return null;
  }
  const [payloadB64, signature] = parts;
  const expected = crypto
    .createHmac('sha256', BROADCAST_KEY)
    .update(payloadB64)
    .digest('base64url');
  if (signature !== expected) {
    return null;
  }
  try {
    const payload = JSON.parse(Buffer.from(payloadB64, 'base64url').toString('utf8'));
    if (!payload?.exp || payload.exp < Math.floor(Date.now() / 1000)) {
      return null;
    }
    if (!Array.isArray(payload.channels) || payload.channels.length === 0) {
      return null;
    }
    return payload;
  } catch {
    return null;
  }
}

function shouldDeliver(clientChannels, messageChannels) {
  if (!messageChannels || messageChannels.length === 0) {
    return true;
  }
  for (const ch of messageChannels) {
    if (ch === 'all' || clientChannels.has(ch)) {
      return true;
    }
  }
  return false;
}

function broadcastMessage(message) {
  const channels = message.channels ?? ['staff'];
  const data = JSON.stringify(message);
  for (const [ws, meta] of clients) {
    if (ws.readyState === ws.OPEN && shouldDeliver(meta.channels, channels)) {
      ws.send(data);
    }
  }
}

const server = http.createServer((req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: true, clients: clients.size }));
    return;
  }

  if (req.method === 'POST' && req.url === '/broadcast') {
    const key = req.headers['x-broadcast-key'] ?? '';
    if (key !== BROADCAST_KEY) {
      res.writeHead(401, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ error: 'Unauthorized' }));
      return;
    }

    let body = '';
    req.on('data', (chunk) => {
      body += chunk;
    });
    req.on('end', () => {
      try {
        const message = JSON.parse(body);
        broadcastMessage(message);
        res.writeHead(202, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ delivered: clients.size }));
      } catch {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Invalid JSON' }));
      }
    });
    return;
  }

  res.writeHead(404);
  res.end();
});

const wss = new WebSocketServer({ server });

wss.on('connection', (ws, req) => {
  const url = new URL(req.url ?? '/', `http://${req.headers.host ?? 'localhost'}`);
  const token = url.searchParams.get('token');
  const payload = verifySubscribeToken(token);
  if (!payload) {
    ws.close(4401, 'Unauthorized');
    return;
  }

  const channels = new Set(payload.channels);
  clients.set(ws, { channels });

  ws.send(
    JSON.stringify({
      type: 'connected',
      payload: { channels: [...channels] },
      sentAt: new Date().toISOString(),
    }),
  );

  ws.on('close', () => {
    clients.delete(ws);
  });
});

server.listen(PORT, () => {
  console.log(`CREDO realtime hub listening on :${PORT} (${clients.size} clients)`);
});
