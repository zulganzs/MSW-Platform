import Busboy from 'busboy';

/**
 * Parse multipart/form-data from a Lambda Function URL event.
 * @param {object} event - Lambda event with headers, body, isBase64Encoded
 * @returns {Promise<{ fields: Record<string, string>, files: Array<{ fieldname: string, buffer: Buffer, filename: string, mimetype: string }> }>}
 */
export function parseMultipart(event) {
  return new Promise((resolve, reject) => {
    const contentType =
      event.headers['content-type'] || event.headers['Content-Type'] || '';

    const busboy = Busboy({
      headers: { 'content-type': contentType },
      limits: { fileSize: 10 * 1024 * 1024 }, // 10 MB
    });

    const fields = {};
    const files = [];

    busboy.on('field', (fieldname, val) => {
      fields[fieldname] = val;
    });

    busboy.on('file', (fieldname, stream, info) => {
      const { filename, mimeType } = info;
      const chunks = [];
      stream.on('data', (chunk) => chunks.push(chunk));
      stream.on('end', () => {
        files.push({
          fieldname,
          buffer: Buffer.concat(chunks),
          filename: filename || 'unknown',
          mimetype: mimeType || 'application/octet-stream',
        });
      });
    });

    busboy.on('finish', () => resolve({ fields, files }));
    busboy.on('error', (err) => reject(err));

    // Write the body to busboy
    const body = event.isBase64Encoded
      ? Buffer.from(event.body, 'base64')
      : Buffer.from(event.body || '');

    busboy.end(body);
  });
}
