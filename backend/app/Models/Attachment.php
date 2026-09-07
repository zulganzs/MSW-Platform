<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'report_id',
        'user_id',
        'file_path',
        'type',
    ];

    public function getUrlAttribute(): string
    {
        // ponytail: root-relative path assumes the public/storage symlink
        // (Storage::url() would emit APP_URL=http://localhost without the :8000
        // dev port); switch to Storage::disk('public')->url() when on S3/CDN.
        return '/storage/' . $this->file_path;
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
