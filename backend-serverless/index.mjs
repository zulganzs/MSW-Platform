import { Router } from './lib/router.mjs';
import { extractUser } from './lib/auth.mjs';

// Import handlers
import * as authHandlers from './handlers/auth.mjs';
import * as reportHandlers from './handlers/reports.mjs';
import * as categoryHandlers from './handlers/categories.mjs';
import * as attachmentHandlers from './handlers/attachments.mjs';
import * as assignmentHandlers from './handlers/assignments.mjs';
import * as statusHandlers from './handlers/status.mjs';
import * as userHandlers from './handlers/users.mjs';

// ─── CORS Headers ───────────────────────────────────────────────
const CORS_HEADERS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type, Authorization, Accept, X-Requested-With',
  'Access-Control-Max-Age': '86400',
};

// ─── Build Response ─────────────────────────────────────────────
function buildResponse(statusCode, body, extraHeaders = {}) {
  return {
    statusCode,
    headers: {
      'Content-Type': 'application/json',
      ...CORS_HEADERS,
      ...extraHeaders,
    },
    body: typeof body === 'string' ? body : JSON.stringify(body),
  };
}

// ─── Parse query string params ──────────────────────────────────
function getQueryParams(event) {
  return event.queryStringParameters || {};
}

// ─── Parse JSON body ────────────────────────────────────────────
function parseBody(event) {
  if (!event.body) return {};
  try {
    const raw = event.isBase64Encoded
      ? Buffer.from(event.body, 'base64').toString('utf-8')
      : event.body;
    return JSON.parse(raw);
  } catch {
    return {};
  }
}

// ─── Check if request is multipart ──────────────────────────────
function isMultipart(event) {
  const ct = event.headers?.['content-type'] || event.headers?.['Content-Type'] || '';
  return ct.includes('multipart/form-data');
}

// ─── Router Setup ───────────────────────────────────────────────
const router = new Router();

// Auth routes
router.add('POST', '/api/register', async (event, body, user, params, query) => {
  return authHandlers.register(body);
});

router.add('POST', '/api/login', async (event, body, user, params, query) => {
  return authHandlers.login(body);
});

router.add('POST', '/api/logout', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return authHandlers.logout(body, user);
});

router.add('GET', '/api/user', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return authHandlers.getUser(body, user);
});

// Report routes
router.add('GET', '/api/reports', async (event, body, user, params, query) => {
  return reportHandlers.index(query, user);
});

router.add('POST', '/api/reports', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return reportHandlers.store(body, user);
});

router.add('GET', '/api/reports/:id', async (event, body, user, params, query) => {
  return reportHandlers.show(params, user);
});

router.add('DELETE', '/api/reports/:id', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return reportHandlers.destroy(params, user);
});

// Crew assigned reports
router.add('GET', '/api/crew/reports', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return reportHandlers.crewReports(query, user);
});

// Category routes
router.add('GET', '/api/categories', async (event, body, user, params, query) => {
  return categoryHandlers.index();
});

router.add('GET', '/api/categories/:id', async (event, body, user, params, query) => {
  return categoryHandlers.show(params);
});

router.add('POST', '/api/categories', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return categoryHandlers.store(body, user);
});

router.add('PUT', '/api/categories/:id', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return categoryHandlers.update(params, body, user);
});

router.add('DELETE', '/api/categories/:id', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return categoryHandlers.destroy(params, user);
});

// Staff-prefixed category routes (match original Laravel API)
router.add('POST', '/api/staff/categories', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return categoryHandlers.store(body, user);
});

router.add('PUT', '/api/staff/categories/:id', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return categoryHandlers.update(params, body, user);
});

router.add('DELETE', '/api/staff/categories/:id', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return categoryHandlers.destroy(params, user);
});

// Attachment routes (multipart upload)
router.add('POST', '/api/attachments', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  // Pass the raw event for multipart parsing
  return attachmentHandlers.store(event, user);
});

// Health check
router.add('GET', '/api/health', async () => {
  return { statusCode: 200, body: { status: 'ok' } };
});

// Staff dashboard health check
router.add('GET', '/api/staff/dashboard', async (event, body, user) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  if (user.role !== 'staff') return { statusCode: 403, body: { message: 'Akses ditolak.' } };
  return { statusCode: 200, body: { status: 'ok' } };
});

// Crew dashboard health check
router.add('GET', '/api/crew/dashboard', async (event, body, user) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  if (user.role !== 'crew') return { statusCode: 403, body: { message: 'Akses ditolak.' } };
  return { statusCode: 200, body: { status: 'ok' } };
});

// Staff: Assignment routes (staff assigns crew to report)
router.add('POST', '/api/staff/reports/:id/assign', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return assignmentHandlers.assignCrew(params, body, user);
});

// Staff: User listing
router.add('GET', '/api/staff/users', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return userHandlers.index(query, user);
});

// Crew: Status update routes
router.add('PATCH', '/api/crew/reports/:id/status', async (event, body, user, params, query) => {
  if (!user) return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  return statusHandlers.updateStatus(params, body, user);
});

// ─── Lambda Handler ─────────────────────────────────────────────
export async function handler(event) {
  // Extract method and path from Lambda Function URL event format
  const method = event.requestContext?.http?.method || event.httpMethod || 'GET';
  const path = event.rawPath || event.path || '/';

  // Handle CORS preflight
  if (method === 'OPTIONS') {
    return buildResponse(204, '');
  }

  // Match route
  const match = router.match(method, path);
  if (!match) {
    return buildResponse(404, { message: 'Not found.' });
  }

  try {
    // Extract user from Bearer token (may be null for public routes)
    const user = extractUser(event);

    // Parse body (JSON for non-multipart, raw event for multipart)
    const body = isMultipart(event) ? {} : parseBody(event);
    const query = getQueryParams(event);

    // Call the matched handler
    const result = await match.handler(event, body, user, match.params, query);

    return buildResponse(result.statusCode, result.body);
  } catch (err) {
    console.error('Unhandled error:', err);
    return buildResponse(500, { message: 'Internal server error.' });
  }
}
