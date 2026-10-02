import { SESClient, SendEmailCommand } from '@aws-sdk/client-ses';

const REGION = process.env.AWS_REGION || 'ap-southeast-1';
const FROM_ADDRESS = process.env.MAIL_FROM_ADDRESS || 'noreply@msw-platform.com';

const ses = new SESClient({ region: REGION });

/**
 * Send an email notifying a citizen that their report has been completed.
 * @param {string} toEmail
 * @param {string} userName
 * @param {string} reportId
 * @param {string} description
 */
export async function sendReportCompletedEmail(toEmail, userName, reportId, description) {
  const subject = 'Laporan Anda Telah Selesai Ditangani';
  const bodyHtml = `
    <h2>Halo ${userName},</h2>
    <p>Laporan Anda dengan ID <strong>${reportId}</strong> telah selesai ditangani.</p>
    <p><strong>Deskripsi:</strong> ${description}</p>
    <p>Terima kasih telah melaporkan masalah sampah di lingkungan Anda. Bersama-sama kita menjaga kebersihan!</p>
    <br/>
    <p>Salam,<br/>Tim MSW Platform</p>
  `;
  const bodyText = `Halo ${userName},\n\nLaporan Anda dengan ID ${reportId} telah selesai ditangani.\n\nDeskripsi: ${description}\n\nTerima kasih telah melaporkan masalah sampah di lingkungan Anda.\n\nSalam,\nTim MSW Platform`;

  await ses.send(
    new SendEmailCommand({
      Source: FROM_ADDRESS,
      Destination: { ToAddresses: [toEmail] },
      Message: {
        Subject: { Data: subject, Charset: 'UTF-8' },
        Body: {
          Html: { Data: bodyHtml, Charset: 'UTF-8' },
          Text: { Data: bodyText, Charset: 'UTF-8' },
        },
      },
    })
  );
}
