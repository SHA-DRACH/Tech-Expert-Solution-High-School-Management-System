<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Database backups (spec section 67).
 *
 * Shells out to mysqldump rather than reimplementing it, writes to the private
 * disk, and prunes old files so the disk cannot fill silently. The result is
 * recorded so an administrator can see backup status in the dashboard.
 */
class BackupDatabase extends Command
{
    protected $signature = 'gsms:backup
        {--keep=14 : How many backups to retain}
        {--path= : Full path to mysqldump if it is not on PATH}';

    protected $description = 'Take a compressed backup of the database';

    public function handle(): int
    {
        $connection = config('database.default');

        if ($connection !== 'mysql') {
            $this->error("Backups are implemented for MySQL; this app is using [{$connection}].");

            return self::FAILURE;
        }

        $config = config("database.connections.{$connection}");
        $binary = $this->option('path') ?: $this->locateMysqldump();

        if ($binary === null) {
            $this->error('mysqldump was not found. Pass its location with --path.');

            return self::FAILURE;
        }

        $filename = sprintf('%s-%s.sql', $config['database'], now()->format('Y-m-d-His'));
        $relative = 'backups/'.$filename;

        Storage::disk('local')->makeDirectory('backups');

        $target = Storage::disk('local')->path($relative);

        $this->info("Backing up {$config['database']}…");

        try {
            $this->dump($binary, $config, $target);
        } catch (ProcessFailedException $e) {
            @unlink($target);

            $this->error('Backup failed: '.trim($e->getProcess()->getErrorOutput()));

            return self::FAILURE;
        }

        $bytes = filesize($target) ?: 0;

        if ($bytes === 0) {
            @unlink($target);

            $this->error('Backup produced an empty file, so it has been discarded.');

            return self::FAILURE;
        }

        $this->recordStatus($relative, $bytes);
        $pruned = $this->prune((int) $this->option('keep'));

        $this->info(sprintf('Wrote %s (%s).', $relative, $this->humanSize($bytes)));

        if ($pruned > 0) {
            $this->line("Pruned {$pruned} older backup(s).");
        }

        return self::SUCCESS;
    }

    protected function dump(string $binary, array $config, string $target): void
    {
        /*
         | The password goes through the environment, never the argument list:
         | anything on the command line is visible to every other process on
         | the machine via the process table.
         */
        $process = new Process([
            $binary,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--quick',
            '--routines',
            '--default-character-set=utf8mb4',
            $config['database'],
        ], env: ['MYSQL_PWD' => (string) $config['password']], timeout: 900);

        $handle = fopen($target, 'wb');

        $process->run(function (string $type, string $buffer) use ($handle) {
            if ($type === Process::OUT) {
                fwrite($handle, $buffer);
            }
        });

        fclose($handle);

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /** Remember the latest result so the dashboard can show backup status. */
    protected function recordStatus(string $path, int $bytes): void
    {
        Storage::disk('local')->put('backups/status.json', json_encode([
            'last_backup_at' => now()->toIso8601String(),
            'path' => $path,
            'size_bytes' => $bytes,
        ], JSON_PRETTY_PRINT));
    }

    /** @return int the number of files removed */
    protected function prune(int $keep): int
    {
        $backups = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $file) => str_ends_with($file, '.sql'))
            ->sortDesc()
            ->values();

        $stale = $backups->slice(max($keep, 1));

        $stale->each(fn (string $file) => Storage::disk('local')->delete($file));

        return $stale->count();
    }

    protected function locateMysqldump(): ?string
    {
        // Laragon and XAMPP both ship one; look there before giving up.
        $candidates = array_merge(
            glob('C:/laragon/bin/mysql/*/bin/mysqldump.exe') ?: [],
            ['C:/xampp/mysql/bin/mysqldump.exe', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'],
        );

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $which = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', 'mysqldump']);
        $which->run();

        $found = trim(strtok($which->getOutput(), "\n") ?: '');

        return $found !== '' ? $found : null;
    }

    protected function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_048_576 => round($bytes / 1_048_576, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }
}
