<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->refuseToRunOutsideTheTestDatabase($app);

        return $app;
    }

    /**
     * A cached bootstrap/cache/config.php wins over phpunit.xml, so the sqlite settings there
     * are silently ignored and RefreshDatabase migrates — and wipes — whatever the cached
     * config points at, which is the development database. Stop before the first migration.
     */
    private function refuseToRunOutsideTheTestDatabase(Application $app): void
    {
        $default = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$default}.database");

        if ($default === 'sqlite' && in_array($database, [':memory:', ''], true)) {
            return;
        }

        throw new RuntimeException(
            "Tests would run against the '{$default}' connection ({$database}), not the in-memory sqlite one. "
            .'Run `php artisan config:clear` — a cached config overrides phpunit.xml.'
        );
    }
}
