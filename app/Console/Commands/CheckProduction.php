<?php

namespace App\Console\Commands;

use App\Support\ProductionChecks;
use Illuminate\Console\Command;

/**
 * Pre-flight check for a live server (D67). Fails (exit 1) on anything that
 * would leak data or break the app; warns on things that only reduce
 * features (no real mailer, placeholder company email).
 */
class CheckProduction extends Command
{
    protected $signature = 'nebo:check-production';

    protected $description = 'Check this installation is safe and complete for production use';

    public function handle(): int
    {
        $checks = ProductionChecks::run();

        $this->table(['', 'Check', 'Detail'], array_map(fn ($c) => [match ($c[0]) {
            'ok' => '<info>OK</info>', 'warn' => '<comment>WARN</comment>', default => '<error>FAIL</error>'
        }, $c[1], $c[0] === 'ok' && $c[1] !== 'Database connection' ? '' : $c[2]], $checks));
        $failed = count(array_filter($checks, fn ($c) => $c[0] === 'fail'));
        $failed ? $this->error("{$failed} check(s) failed.") : $this->info('Ready for production.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
