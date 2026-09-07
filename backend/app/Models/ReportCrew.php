<?php

namespace App\Models;

use Database\Factories\ReportCrewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCrew extends Model
{
    /** @use HasFactory<ReportCrewFactory> */
    use HasFactory;

    protected $table = 'report_crew';

    protected $fillable = [
        'report_id',
        'crew_user_id',
        'staff_user_id',
        'assigned_at',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function crewUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'crew_user_id');
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
