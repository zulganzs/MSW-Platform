import { scanAll } from '../lib/db.mjs';

/**
 * GET /api/users (staff only)
 */
export async function index(queryParams, user) {
  if (!user) {
    return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  }

  if (user.role !== 'staff') {
    return { statusCode: 403, body: { message: 'Unauthorized.' } };
  }

  const roleFilter = queryParams?.role;

  let filter = null;
  if (roleFilter) {
    filter = {
      expression: '#r = :role',
      values: { ':role': roleFilter },
      names: { '#r': 'role' },
    };
  }

  const users = await scanAll('users', filter);

  // Return only id, name, email (strip password and other sensitive fields)
  const result = users.map((u) => ({
    id: u.id,
    name: u.name,
    email: u.email,
    role: u.role,
  }));

  // Sort by name
  result.sort((a, b) => (a.name || '').localeCompare(b.name || ''));

  return { statusCode: 200, body: result };
}
