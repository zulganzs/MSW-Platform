import {
  S3Client,
  PutObjectCommand,
  DeleteObjectCommand,
} from '@aws-sdk/client-s3';

const REGION = process.env.AWS_REGION || 'ap-southeast-1';
const BUCKET = process.env.MSW_ATTACHMENTS_BUCKET || 'msw-attachments';
const CLOUDFRONT_DOMAIN = process.env.CLOUDFRONT_DOMAIN || '';

const s3 = new S3Client({ region: REGION });

/**
 * Upload a file buffer to S3.
 * @param {Buffer} buffer
 * @param {string} key - S3 object key, e.g. "attachments/uuid.jpg"
 * @param {string} contentType - MIME type
 * @returns {string} the S3 key
 */
export async function uploadFile(buffer, key, contentType) {
  await s3.send(
    new PutObjectCommand({
      Bucket: BUCKET,
      Key: key,
      Body: buffer,
      ContentType: contentType,
    })
  );
  return key;
}

/**
 * Get a publicly-accessible URL for an S3 object.
 * Uses CloudFront domain if configured, otherwise falls back to S3 URL.
 * @param {string} key
 * @returns {string}
 */
export function getFileUrl(key) {
  if (CLOUDFRONT_DOMAIN) {
    const domain = CLOUDFRONT_DOMAIN.replace(/\/$/, '');
    return `https://${domain}/${key}`;
  }
  return `https://${BUCKET}.s3.${REGION}.amazonaws.com/${key}`;
}

/**
 * Delete a file from S3.
 * @param {string} key
 */
export async function deleteFile(key) {
  await s3.send(
    new DeleteObjectCommand({
      Bucket: BUCKET,
      Key: key,
    })
  );
}
