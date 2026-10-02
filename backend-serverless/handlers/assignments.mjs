import { getItem, putItem, queryIndex, updateItem } from '../lib/db.mjs';
import { queryAll } from '../lib/db.mjs';
import { getFileUrl } from '../lib/s3.mjs';

/**
 * Format a Date to Laravel-compatible ISO 8601 string.
 */
function laravelDate(date) {
  return date.toISOString().replace(/(\.\d{3})Z$/, '$1000Z');
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
 * POST /api/reports/:id/assign (staff only)
 */
export async function assignCrew(params, body, user) {
  if (!user) {
    return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  }

  if (user.role !== 'staff') {
    return { statusCode: 403, body: { message: 'Unauthorized.' } };
  }

  const reportId = params.id;
  const crewUserId = body.crew_user_id;

  // Validate required field
  if (!crewUserId) {
    return {
      statusCode: 422,
      body: {
        message: 'The given data was invalid.',
        errors: { crew_user_id: ['The crew user id field is required.'] },
      },
    };
  }

  // Validate report exists
  const report = await getItem('reports', { id: reportId });
  if (!report) {
    return { statusCode: 404, body: { message: 'Report not found.' } };
  }

  // Validate crew user exists and has role=crew
  const crewUser = await getItem('users', { id: crewUserId });
  if (!crewUser || crewUser.role !== 'crew') {
    return {
      statusCode: 422,
      body: {
        message: 'The given data was invalid.',
        errors: { crew_user_id: ['The selected user is not a valid crew member.'] },
      },
    };
  }

  // Check not already assigned
  const existing = await queryIndex('report-crew', null, {
    expression: 'reportId = :reportId AND crewUserId = :crewUserId',
    values: { ':reportId': reportId, ':crewUserId': crewUserId },
  });

  if (existing.items.length > 0) {
    return {
      statusCode: 422,
      body: { message: 'This crew member is already assigned to this report.' },
    };
  }

  // Create assignment record
  const now = new Date();
  await putItem('report-crew', {
    reportId,
    crewUserId,
    assigned_at: laravelDate(now),
    created_at: laravelDate(now),
    updated_at: laravelDate(now),
  });

  // Update report status to assigned
  await updateItem('reports', { id: reportId }, {
    status: 'assigned',
    updated_at: laravelDate(now),
  });

  // Return updated report with relations
  const updatedReport = await getItem('reports', { id: reportId });

  // Load crew assignments
  const crewAssignments = await queryAll('report-crew', null, {
    expression: 'reportId = :reportId',
    values: { ':reportId': reportId },
  });

  // Load category
  let category = null;
  if (updatedReport.category_id) {
    category = await getItem('categories', { id: updatedReport.category_id });
  }

  // Load attachments
  const attachments = await queryAll('attachments', 'reportId-index', {
    expression: 'reportId = :reportId',
    values: { ':reportId': reportId },
  });
  const enrichedAttachments = attachments.map((att) => ({
    ...att,
    file_path: att.s3Key ? getFileUrl(att.s3Key) : att.file_path,
  }));

  // Load crew users
  const { batchGetItems } = await import('../lib/db.mjs');
  let crews = [];
  if (crewAssignments.length > 0) {
    const crewKeys = crewAssignments.map((a) => ({ id: a.crewUserId }));
    const crewUsers = await batchGetItems('users', crewKeys);
    crews = crewUsers.map(sanitizeUser);
  }

  // Load owner user
  let ownerUser = null;
  if (updatedReport.userId) {
    const u = await getItem('users', { id: updatedReport.userId });
    ownerUser = sanitizeUser(u);
  }

  return {
    statusCode: 200,
    body: {
      message: 'Crew assigned successfully.',
      report: {
        ...updatedReport,
        category,
        attachments: enrichedAttachments,
        crews,
        user: ownerUser,
      },
    },
  };
}
