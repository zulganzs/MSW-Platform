import { v4 as uuidv4 } from 'uuid';
import {
  putItem,
  getItem,
  queryIndex,
  queryAll,
  scanAll,
  deleteItem,
  batchGetItems,
} from '../lib/db.mjs';
import { getFileUrl } from '../lib/s3.mjs';

/**
 * Format a Date to Laravel-compatible ISO 8601 string.
 */
function laravelDate(date) {
  return date.toISOString().replace(/(\.\d{3})Z$/, '$1000Z');
}

/**
 * Remove user info from anonymous reports.
 */
function maskAnonymous(report) {
  if (report.is_anonymous) {
    const { userId, user, ...rest } = report;
    return { ...rest, userId: null, user: null };
  }
  return report;
}

/**
 * Strip password from user objects.
 */
function sanitizeUser(user) {
  if (!user) return null;
  const { password, ...rest } = user;
  return rest;
}

/**
 * Load full report with relations (category, attachments, crews).
 */
async function loadReportRelations(report) {
  // Load category
  let category = null;
  if (report.category_id) {
    category = await getItem('categories', { id: report.category_id });
  }

  // Load attachments
  const attachmentResult = await queryAll('attachments', 'reportId-index', {
    expression: 'reportId = :reportId',
    values: { ':reportId': report.id },
  });
  const attachments = attachmentResult.map((att) => ({
    ...att,
    file_path: att.s3Key ? getFileUrl(att.s3Key) : att.file_path,
  }));

  // Load crew assignments
  const crewAssignments = await queryAll('report-crew', null, {
    expression: 'reportId = :reportId',
    values: { ':reportId': report.id },
  });

  // Load crew user details
  let crews = [];
  if (crewAssignments.length > 0) {
    const crewKeys = crewAssignments.map((a) => ({ id: a.crewUserId }));
    const crewUsers = await batchGetItems('users', crewKeys);
    crews = crewUsers.map(sanitizeUser);
  }

  // Load report owner user
  let user = null;
  if (report.userId) {
    const u = await getItem('users', { id: report.userId });
    user = sanitizeUser(u);
  }

  return {
    ...report,
    category,
    attachments,
    crews,
    user,
  };
}

/**
 * Check if a user can view a report based on visibility rules.
 */
function canViewReport(report, authUser) {
  // Staff and crew can see everything
  if (authUser && (authUser.role === 'staff' || authUser.role === 'crew')) {
    return true;
  }

  // Public and anonymous reports are visible to everyone
  if (report.visibility === 'public' || report.visibility === 'anonymous') {
    return true;
  }

  // Private reports: only visible to the owner
  if (report.visibility === 'private') {
    return authUser && authUser.id === report.userId;
  }

  // Default: allow if public/anonymous
  return false;
}

/**
 * Filter reports based on user's visibility permissions.
 */
function filterByVisibility(reports, authUser) {
  return reports.filter((r) => canViewReport(r, authUser));
}

/**
 * GET /api/reports
 */
export async function index(queryParams, user) {
  const page = parseInt(queryParams?.page || '1', 10);
  const perPage = parseInt(queryParams?.per_page || '15', 10);
  const statusFilter = queryParams?.status;
  const categoryFilter = queryParams?.category_id;

  // Scan all reports (portfolio-scale data)
  let allReports = await scanAll('reports', null);

  // Apply status filter
  if (statusFilter) {
    allReports = allReports.filter((r) => r.status === statusFilter);
  }

  // Apply category filter
  if (categoryFilter) {
    allReports = allReports.filter((r) => r.category_id === categoryFilter);
  }

  // Apply visibility filter
  allReports = filterByVisibility(allReports, user);

  // Sort by created_at descending
  allReports.sort((a, b) => (b.created_at || '').localeCompare(a.created_at || ''));

  // Paginate
  const total = allReports.length;
  const lastPage = Math.max(1, Math.ceil(total / perPage));
  const start = (page - 1) * perPage;
  const paginatedReports = allReports.slice(start, start + perPage);

  // Load relations for each report in the page
  const enriched = await Promise.all(
    paginatedReports.map(async (r) => {
      const full = await loadReportRelations(r);
      return maskAnonymous(full);
    })
  );

  const baseUrl = '/api/reports';
  return {
    statusCode: 200,
    body: {
      data: enriched,
      current_page: page,
      per_page: perPage,
      total,
      last_page: lastPage,
      next_page_url: page < lastPage ? `${baseUrl}?page=${page + 1}` : null,
      prev_page_url: page > 1 ? `${baseUrl}?page=${page - 1}` : null,
    },
  };
}

/**
 * GET /api/reports/:id
 */
export async function show(params, user) {
  const report = await getItem('reports', { id: params.id });
  if (!report) {
    return { statusCode: 404, body: { message: 'Report not found.' } };
  }

  if (!canViewReport(report, user)) {
    return { statusCode: 403, body: { message: 'Unauthorized.' } };
  }

  const full = await loadReportRelations(report);
  return { statusCode: 200, body: maskAnonymous(full) };
}

/**
 * POST /api/reports
 */
export async function store(body, user) {
  if (!user) {
    return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  }

  // Validate required fields
  const errors = {};
  if (!body.description || typeof body.description !== 'string' || body.description.trim() === '') {
    errors.description = ['The description field is required.'];
  }
  if (!body.category_id) {
    errors.category_id = ['The category id field is required.'];
  }
  if (!body.latitude) {
    errors.latitude = ['The latitude field is required.'];
  }
  if (!body.longitude) {
    errors.longitude = ['The longitude field is required.'];
  }
  if (!body.visibility || !['public', 'private', 'anonymous'].includes(body.visibility)) {
    errors.visibility = ['The visibility field is required and must be public, private, or anonymous.'];
  }

  if (Object.keys(errors).length > 0) {
    return {
      statusCode: 422,
      body: { message: 'The given data was invalid.', errors },
    };
  }

  // Validate category exists
  const category = await getItem('categories', { id: body.category_id });
  if (!category) {
    return {
      statusCode: 422,
      body: {
        message: 'The given data was invalid.',
        errors: { category_id: ['The selected category is invalid.'] },
      },
    };
  }

  const now = new Date();
  const report = {
    id: uuidv4(),
    userId: user.id,
    category_id: body.category_id,
    description: body.description.trim(),
    latitude: parseFloat(body.latitude),
    longitude: parseFloat(body.longitude),
    visibility: body.visibility,
    is_anonymous: body.visibility === 'anonymous',
    status: 'submitted',
    address: body.address || null,
    created_at: laravelDate(now),
    updated_at: laravelDate(now),
  };

  await putItem('reports', report);

  const full = await loadReportRelations(report);
  return { statusCode: 201, body: maskAnonymous(full) };
}

/**
 * DELETE /api/reports/:id
 */
export async function destroy(params, user) {
  if (!user) {
    return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  }

  const report = await getItem('reports', { id: params.id });
  if (!report) {
    return { statusCode: 404, body: { message: 'Report not found.' } };
  }

  // Only owner can delete, and only if status is submitted
  if (report.userId !== user.id && user.role !== 'staff') {
    return { statusCode: 403, body: { message: 'Unauthorized.' } };
  }

  if (report.status !== 'submitted') {
    return {
      statusCode: 422,
      body: { message: 'Only reports with status "submitted" can be deleted.' },
    };
  }

  await deleteItem('reports', { id: params.id });
  return { statusCode: 200, body: { message: 'Report deleted successfully.' } };
}

/**
 * GET /api/crew/reports (crew's assigned reports)
 */
export async function crewReports(queryParams, user) {
  if (!user) {
    return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  }

  if (user.role !== 'crew' && user.role !== 'staff') {
    return { statusCode: 403, body: { message: 'Unauthorized.' } };
  }

  const page = parseInt(queryParams?.page || '1', 10);
  const perPage = parseInt(queryParams?.per_page || '15', 10);
  const statusFilter = queryParams?.status;

  // Get all assignments for this crew member
  const assignments = await queryAll('report-crew', 'crewUserId-index', {
    expression: 'crewUserId = :crewUserId',
    values: { ':crewUserId': user.id },
  });

  if (assignments.length === 0) {
    return {
      statusCode: 200,
      body: {
        data: [],
        current_page: page,
        per_page: perPage,
        total: 0,
        last_page: 1,
        next_page_url: null,
        prev_page_url: null,
      },
    };
  }

  // Batch get the reports
  const reportKeys = assignments.map((a) => ({ id: a.reportId }));
  let reports = await batchGetItems('reports', reportKeys);

  // Apply status filter
  if (statusFilter) {
    reports = reports.filter((r) => r.status === statusFilter);
  }

  // Sort by created_at descending
  reports.sort((a, b) => (b.created_at || '').localeCompare(a.created_at || ''));

  // Paginate
  const total = reports.length;
  const lastPage = Math.max(1, Math.ceil(total / perPage));
  const start = (page - 1) * perPage;
  const paginatedReports = reports.slice(start, start + perPage);

  // Load relations
  const enriched = await Promise.all(
    paginatedReports.map(async (r) => {
      const full = await loadReportRelations(r);
      return maskAnonymous(full);
    })
  );

  const baseUrl = '/api/crew/reports';
  return {
    statusCode: 200,
    body: {
      data: enriched,
      current_page: page,
      per_page: perPage,
      total,
      last_page: lastPage,
      next_page_url: page < lastPage ? `${baseUrl}?page=${page + 1}` : null,
      prev_page_url: page > 1 ? `${baseUrl}?page=${page - 1}` : null,
    },
  };
}
