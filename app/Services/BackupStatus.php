<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Reads what the backup command last recorded, so an administrator can see at
 * a glance whether backups are actually running (spec section 67).
 */
class BackupStatus
{
    protected const STATUS_FILE = 'backups/status.json';

    /** A backup older than this is treated as overdue. */
    protected const OVERDUE_AFTER_HOURS = 36;

    /** @return array<string, mixed> */
    public function current(): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::STATUS_FILE)) {
            return [
                'configured' => false,
                'last_backup_at' => null,
                'is_overdue' => true,
                'size' => null,
                'count' => 0,
            ];
        }

        $status = json_decode($disk->get(self::STATUS_FILE), true) ?: [];

        $lastRun = isset($status['last_backup_at'])
            ? Carbon::parse($status['last_backup_at'])
            : null;

        return [
            'configured' => true,
            'last_backup_at' => $lastRun,
            'is_overdue' => $lastRun === null || $lastRun->lt(now()->subHours(self::OVERDUE_AFTER_HOURS)),
            'size' => $this->humanSize((int) ($status['size_bytes'] ?? 0)),
            'count' => count($this->files()),
        ];
    }

    /** @return array<int, string> newest first */
    public function files(): array
    {
        return collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $file) => str_ends_with($file, '.sql'))
            ->sortDesc()
            ->values()
            ->all();
    }

    protected function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_073_741_824 => round($bytes / 1_073_741_824, 1).' GB',
            $bytes >= 1_048_576 => round($bytes / 1_048_576, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }
}
