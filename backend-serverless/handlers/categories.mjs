import { v4 as uuidv4 } from 'uuid';
import { putItem, getItem, scanAll, updateItem, deleteItem } from '../lib/db.mjs';

/**
 * Format a Date to Laravel-compatible ISO 8601 string.
 */
function laravelDate(date) {
  return date.toISOString().replace(/(\.\d{3})Z$/, '$1000Z');
}

/**
 * GET /api/categories
 */
export async function index() {
  const categories = await scanAll('categories', null);
  // Sort by created_at ascending for consistent ordering
  categories.sort((a, b) => (a.created_at || '').localeCompare(b.created_at || ''));
  return { statusCode: 200, body: categories };
}

/**
 * GET /api/categories/:id
 */
export async function show(params) {
  const category = await getItem('categories', { id: params.id });
  if (!category) {
    return { statusCode: 404, body: { message: 'Category not found.' } };
  }
  return { statusCode: 200, body: category };
}

/**
 * POST /api/categories (staff only)
 */
export async function store(body, user) {
  if (!user || user.role !== 'staff') {
    return { statusCode: 403, body: { message: 'Unauthorized.' } };
  }

  const errors = {};
  if (!body.name || typeof body.name !== 'string' || body.name.trim() === '') {
    errors.name = ['The name field is required.'];
  }

  if (Object.keys(errors).length > 0) {
    return {
      statusCode: 422,
      body: { message: 'The given data was invalid.', errors },
    };
  }

  const now = new Date();
  const category = {
    id: uuidv4(),
    name: body.name.trim(),
    description: body.description?.trim() || null,
    icon: body.icon?.trim() || null,
    created_at: laravelDate(now),
    updated_at: laravelDate(now),
  };

  await putItem('categories', category);
  return { statusCode: 201, body: category };
}

/**
 * PUT /api/categories/:id (staff only)
 */
export async function update(params, body, user) {
  if (!user || user.role !== 'staff') {
    return { statusCode: 403, body: { message: 'Unauthorized.' } };
  }

  const category = await getItem('categories', { id: params.id });
  if (!category) {
    return { statusCode: 404, body: { message: 'Category not found.' } };
  }

  const errors = {};
  if (body.name !== undefined && (typeof body.name !== 'string' || body.name.trim() === '')) {
    errors.name = ['The name field must be a non-empty string.'];
  }

  if (Object.keys(errors).length > 0) {
    return {
      statusCode: 422,
      body: { message: 'The given data was invalid.', errors },
    };
  }

  const updates = {
    updated_at: laravelDate(new Date()),
  };
  if (body.name !== undefined) updates.name = body.name.trim();
  if (body.description !== undefined) updates.description = body.description?.trim() || null;
  if (body.icon !== undefined) updates.icon = body.icon?.trim() || null;

  await updateItem('categories', { id: params.id }, updates);

  const updated = { ...category, ...updates };
  return { statusCode: 200, body: updated };
}

/**
 * DELETE /api/categories/:id (staff only)
 */
export async function destroy(params, user) {
  if (!user || user.role !== 'staff') {
    return { statusCode: 403, body: { message: 'Unauthorized.' } };
  }

  const category = await getItem('categories', { id: params.id });
  if (!category) {
    return { statusCode: 404, body: { message: 'Category not found.' } };
  }

  await deleteItem('categories', { id: params.id });
  return { statusCode: 200, body: { message: 'Category deleted successfully.' } };
}
