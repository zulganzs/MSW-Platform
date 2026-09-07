<?php

namespace App\Observers;

use App\Mail\ReportCompletedMail;
use App\Models\Report;
use Illuminate\Support\Facades\Mail;

class ReportObserver
{
    public function updated(Report $report): void
    {
        // Only when status transitions TO completed (re-saving a completed report must not re-send).
        if (! $report->isDirty('status') || $report->status !== 'completed') {
            return;
        }

        // Anonymous reports never trigger a citizen email.
        if ($report->visibility === 'anonymous') {
            return;
        }

        Mail::to($report->user->email)->send(new ReportCompletedMail($report));
    }
}
