<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Induk riwayat scan PHP2Shell. Satu scan dapat mencakup satu atau lebih
 * target (lihat Php2ShellScanTarget).
 */
class Php2ShellScan extends Model
{
    protected $table = 'php2shell_scans';

    protected $fillable = [
        'user_id', 'mode', 'total_targets', 'completed_targets',
        'status', 'scanner_version', 'duration_ms', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'total_targets' => 'integer',
        'completed_targets' => 'integer',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_QUEUED = 'QUEUED';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Php2ShellScanTarget::class, 'php2shell_scan_id');
    }

    public function scopeOwnedBy($query, ?int $userId)
    {
        if ($userId === null) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    public function progressPercent(): int
    {
        if ($this->total_targets <= 0) {
            return 0;
        }

        return (int) round(($this->completed_targets / $this->total_targets) * 100);
    }
}
