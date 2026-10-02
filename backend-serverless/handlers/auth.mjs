import { v4 as uuidv4 } from 'uuid';
import { hashPassword, verifyPassword, createToken } from '../lib/auth.mjs';
import { putItem, getItem, queryIndex } from '../lib/db.mjs';

/**
 * Format a Date to Laravel-compatible ISO 8601 with microseconds.
 */
function laravelDate(date) {
  return date.toISOString().replace(/(\.\d{3})Z$/, '$1000Z');
}

/**
 * Strip password from a user object before returning.
 */
function sanitizeUser(user) {
  if (!user) return null;
  const { password, ...rest } = user;
  return rest;
}

/**
 * POST /api/register
 */
export async function register(body) {
  // Validate required fields
  const errors = {};
  if (!body.name || typeof body.name !== 'string' || body.name.trim() === '') {
    errors.name = ['The name field is required.'];
  }
  if (!body.email || typeof body.email !== 'string' || body.email.trim() === '') {
    errors.email = ['The email field is required.'];
  }
  if (!body.password || typeof body.password !== 'string' || body.password.length < 8) {
    errors.password = ['The password field is required and must be at least 8 characters.'];
  }
  if (body.password && body.password !== body.password_confirmation) {
    errors.password_confirmation = ['The password confirmation does not match.'];
  }

  if (Object.keys(errors).length > 0) {
    return {
      statusCode: 422,
      body: { message: 'The given data was invalid.', errors },
    };
  }

  // Check email uniqueness
  const existing = await queryIndex('users', 'email-index', {
    expression: 'email = :email',
    values: { ':email': body.email.trim().toLowerCase() },
  });

  if (existing.items.length > 0) {
    return {
      statusCode: 422,
      body: {
        message: 'The given data was invalid.',
        errors: { email: ['The email has already been taken.'] },
      },
    };
  }

  const now = new Date();
  const user = {
    id: uuidv4(),
    name: body.name.trim(),
    email: body.email.trim().toLowerCase(),
    password: await hashPassword(body.password),
    role: 'citizen',
    created_at: laravelDate(now),
    updated_at: laravelDate(now),
  };

  await putItem('users', user);

  const token = createToken({ sub: user.id, email: user.email, role: user.role });

  return {
    statusCode: 201,
    body: {
      token,
      user: sanitizeUser(user),
    },
  };
}

/**
 * POST /api/login
 */
export async function login(body) {
  if (!body.email || !body.password) {
    return {
      statusCode: 422,
      body: {
        message: 'The given data was invalid.',
        errors: {
          ...(body.email ? {} : { email: ['The email field is required.'] }),
          ...(body.password ? {} : { password: ['The password field is required.'] }),
        },
      },
    };
  }

  const result = await queryIndex('users', 'email-index', {
    expression: 'email = :email',
    values: { ':email': body.email.trim().toLowerCase() },
  });

  const user = result.items[0];
  if (!user) {
    return {
      statusCode: 401,
      body: { message: 'Email atau password salah.' },
    };
  }

  const valid = await verifyPassword(body.password, user.password);
  if (!valid) {
    return {
      statusCode: 401,
      body: { message: 'Email atau password salah.' },
    };
  }

  const token = createToken({ sub: user.id, email: user.email, role: user.role });

  return {
    statusCode: 200,
    body: {
      token,
      user: sanitizeUser(user),
    },
  };
}

/**
 * POST /api/logout (requires auth)
 */
export async function logout(body, user) {
  return {
    statusCode: 200,
    body: { message: 'Berhasil logout' },
  };
}

/**
 * GET /api/user (requires auth)
 */
export async function getUser(body, user) {
  const fullUser = await getItem('users', { id: user.id });
  if (!fullUser) {
    return { statusCode: 404, body: { message: 'User not found.' } };
  }
  return { statusCode: 200, body: sanitizeUser(fullUser) };
}
