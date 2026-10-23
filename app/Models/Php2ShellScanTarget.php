<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil pemeriksaan per target dalam satu scan PHP2Shell.
 */
class Php2ShellScanTarget extends Model
{
    protected $table = 'php2shell_scan_targets';

    protected $fillable = [
        'php2shell_scan_id', 'target_url', 'normalized_url', 'hostname',
        'verdict', 'reason', 'evidence', 'sapi', 'x_powered_by',
        'duration_ms', 'checked_at',
    ];

    protected $casts = [
        'duration_ms' => 'integer',
        'checked_at' => 'datetime',
    ];

    public const VERDICT_VULNERABLE = 'VULNERABLE';
    public const VERDICT_NOT_VULNERABLE = 'NOT VULNERABLE';
    public const VERDICT_INCONCLUSIVE = 'INCONCLUSIVE';
    public const VERDICT_ERROR = 'ERROR';
    public const VERDICT_TIMEOUT = 'TIMEOUT';
    public const VERDICT_UNREACHABLE = 'UNREACHABLE';

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Php2ShellScan::class, 'php2shell_scan_id');
    }

    public function severityClass(): string
    {
        return match ($this->verdict) {
            self::VERDICT_VULNERABLE => 'text-red-400 font-bold',
            self::VERDICT_NOT_VULNERABLE => 'text-[#b8ffcf]',
            self::VERDICT_INCONCLUSIVE => 'text-yellow-400',
            self::VERDICT_TIMEOUT => 'text-orange-400',
            self::VERDICT_UNREACHABLE => 'text-ink/50 dark:text-[#b8ffcf]/50',
            default => 'text-ink/50 dark:text-[#b8ffcf]/50',
        };
    }
}
