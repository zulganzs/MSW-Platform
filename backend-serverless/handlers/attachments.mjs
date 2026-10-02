import { v4 as uuidv4 } from 'uuid';
import { putItem, getItem } from '../lib/db.mjs';
import { uploadFile, getFileUrl } from '../lib/s3.mjs';
import { parseMultipart } from '../lib/multipart.mjs';

/**
 * Format a Date to Laravel-compatible ISO 8601 string.
 */
function laravelDate(date) {
  return date.toISOString().replace(/(\.\d{3})Z$/, '$1000Z');
}

/**
 * Allowed image MIME types.
 */
const ALLOWED_MIMES = [
  'image/jpeg',
  'image/jpg',
  'image/png',
  'image/gif',
  'image/webp',
];

/**
 * Get file extension from MIME type.
 */
function extFromMime(mime) {
  const map = {
    'image/jpeg': 'jpg',
    'image/jpg': 'jpg',
    'image/png': 'png',
    'image/gif': 'gif',
    'image/webp': 'webp',
  };
  return map[mime] || 'bin';
}

/**
 * POST /api/attachments (multipart upload)
 */
export async function store(event, user) {
  if (!user) {
    return { statusCode: 401, body: { message: 'Unauthenticated.' } };
  }

  // Parse multipart form data
  let parsed;
  try {
    parsed = await parseMultipart(event);
  } catch (err) {
    return { statusCode: 400, body: { message: 'Failed to parse multipart form data.' } };
  }

  const { fields, files } = parsed;
  const reportId = fields.report_id;
  const type = fields.type; // "submission" or "closure"

  // Validate required fields
  const errors = {};
  if (!reportId) {
    errors.report_id = ['The report id field is required.'];
  }
  if (!type || !['submission', 'closure'].includes(type)) {
    errors.type = ['The type field is required and must be submission or closure.'];
  }
  if (files.length === 0) {
    errors.file = ['The file field is required.'];
  }

  if (Object.keys(errors).length > 0) {
    return {
      statusCode: 422,
      body: { message: 'The given data was invalid.', errors },
    };
  }

  const file = files[0];

  // Validate file is an image
  if (!ALLOWED_MIMES.includes(file.mimetype)) {
    return {
      statusCode: 422,
      body: {
        message: 'The given data was invalid.',
        errors: { file: ['The file must be an image (jpeg, png, gif, webp).'] },
      },
    };
  }

  // Validate report exists
  const report = await getItem('reports', { id: reportId });
  if (!report) {
    return {
      statusCode: 422,
      body: {
        message: 'The given data was invalid.',
        errors: { report_id: ['The selected report is invalid.'] },
      },
    };
  }

  // Role-based access control for attachment types
  if (type === 'submission') {
    // Only the report owner (citizen) can upload submission attachments
    // and only when status is "submitted"
    if (report.userId !== user.id && user.role !== 'staff') {
      return { statusCode: 403, body: { message: 'Unauthorized.' } };
    }
    if (report.status !== 'submitted') {
      return {
        statusCode: 422,
        body: { message: 'Submission attachments can only be added to reports with status "submitted".' },
      };
    }
  } else if (type === 'closure') {
    // Only assigned crew can upload closure attachments
    // and only when status is "in_progress"
    if (user.role !== 'crew' && user.role !== 'staff') {
      return { statusCode: 403, body: { message: 'Unauthorized.' } };
    }
    if (report.status !== 'in_progress') {
      return {
        statusCode: 422,
        body: { message: 'Closure attachments can only be added to reports with status "in_progress".' },
      };
    }
  }

  // Upload to S3
  const ext = extFromMime(file.mimetype);
  const attachmentId = uuidv4();
  const s3Key = `attachments/${attachmentId}.${ext}`;

  await uploadFile(file.buffer, s3Key, file.mimetype);

  const now = new Date();
  const attachment = {
    id: attachmentId,
    reportId,
    s3Key,
    file_path: getFileUrl(s3Key),
    original_filename: file.filename,
    mime_type: file.mimetype,
    type,
    created_at: laravelDate(now),
    updated_at: laravelDate(now),
  };

  await putItem('attachments', attachment);

  return { statusCode: 201, body: attachment };
}
