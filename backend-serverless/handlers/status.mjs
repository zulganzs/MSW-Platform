import {
  getItem,
  updateItem,
  queryAll,
  queryIndex,
  batchGetItems,
} from '../lib/db.mjs';
import { getFileUrl } from '../lib/s3.mjs';
import { sendReportCompletedEmail } from '../lib/email.mjs';

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
 * Allowed status transitions (forward-only).
 */
const VALID_TRANSITIONS = {
  assigned: 'in_progress',
  in_progress: 'completed',
};

/**
 * PATCH /api/reports/:id/status (crew updates status)
 */
export async function updateStatus(params, body, user) {
  if (!user) {
    return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  }

  if (user.role !== 'crew' && user.role !== 'staff') {
    return { statusCode: 403, body: { message: 'Unauthorized.' } };
  }

  const reportId = params.id;
  const newStatus = body.status;

  // Validate required field
  if (!newStatus) {
    return {
      statusCode: 422,
      body: {
        message: 'The given data was invalid.',
        errors: { status: ['The status field is required.'] },
      },
    };
  }

  // Validate report exists
  const report = await getItem('reports', { id: reportId });
  if (!report) {
    return { statusCode: 404, body: { message: 'Report not found.' } };
  }

  // Check crew is assigned to this report (unless staff)
  if (user.role === 'crew') {
    const assignment = await queryIndex('report-crew', null, {
      expression: 'reportId = :reportId AND crewUserId = :crewUserId',
      values: { ':reportId': reportId, ':crewUserId': user.id },
    });

    if (assignment.items.length === 0) {
      return { statusCode: 403, body: { message: 'You are not assigned to this report.' } };
    }
  }

  // Validate forward-only transition
  const expectedNext = VALID_TRANSITIONS[report.status];
  if (!expectedNext || expectedNext !== newStatus) {
    return {
      statusCode: 422,
      body: {
        message: `Invalid status transition from "${report.status}" to "${newStatus}". Allowed: ${report.status} → ${expectedNext || 'none'}.`,
      },
    };
  }

  // Update the status
  const now = new Date();
  const updates = {
    status: newStatus,
    updated_at: laravelDate(now),
  };

  // Add completed_at timestamp
  if (newStatus === 'completed') {
    updates.completed_at = laravelDate(now);
  }

  await updateItem('reports', { id: reportId }, updates);

  // If completed and not anonymous, send email notification
  if (newStatus === 'completed' && !report.is_anonymous && report.userId) {
    try {
      const owner = await getItem('users', { id: report.userId });
      if (owner && owner.email) {
        await sendReportCompletedEmail(
          owner.email,
          owner.name,
          report.id,
          report.description
        );
      }
    } catch (emailErr) {
      // Log but don't fail the request
      console.error('Failed to send completion email:', emailErr.message);
    }
  }

  // Load updated report with relations
  const updatedReport = { ...report, ...updates };

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

  // Load crew assignments
  const crewAssignments = await queryAll('report-crew', null, {
    expression: 'reportId = :reportId',
    values: { ':reportId': reportId },
  });

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
      message: `Report status updated to "${newStatus}".`,
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
