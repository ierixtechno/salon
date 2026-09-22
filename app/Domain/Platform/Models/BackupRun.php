<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    public const RUNNING = 'running';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    protected $fillable = [
        'trigger', 'status', 'file_name', 'size_bytes', 'table_count', 'row_count',
        'error_message', 'platform_admin_id', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }

    public function succeeded(): bool
    {
        return $this->status === self::SUCCESS;
    }

    /** Absolute path of the archive, or null if it never produced one. */
    public function path(): ?string
    {
        return $this->file_name ? rtrim(config('backup.path'), '/\\').DIRECTORY_SEPARATOR.basename($this->file_name) : null;
    }

    /** Pruned by retention (or deleted by hand) after the run itself succeeded. */
    public function fileExists(): bool
    {
        $path = $this->path();

        return $path !== null && is_file($path);
    }

    public function durationSeconds(): ?int
    {
        return $this->finished_at ? (int) $this->started_at->diffInSeconds($this->finished_at) : null;
    }
}
