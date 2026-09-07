<?php

namespace App\Mail;

use App\Models\Report;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReportCompletedMail extends Mailable
{
    public function __construct(public Report $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Laporan Anda Telah Selesai',
        );
    }

    public function content(): Content
    {
        // Inline HTML body (no Blade view): user-facing text must be Bahasa Indonesia.
        return new Content(htmlString: sprintf(
            '<p>Halo %s,</p>'
            .'<p>Laporan Anda (ID #%d) telah selesai ditangani oleh petugas.</p>'
            .'<p>Deskripsi laporan: %s</p>'
            .'<p>Terima kasih telah membantu menjaga kebersihan lingkungan.</p>'
            .'<p>Hormat kami,<br>Tim Pengelola</p>',
            e($this->report->user->name),
            $this->report->id,
            e($this->report->description),
        ));
    }
}
