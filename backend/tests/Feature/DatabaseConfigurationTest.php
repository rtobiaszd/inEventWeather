<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->clearEnvironment([
            'APP_ENV',
            'CACHE_DRIVER',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'QUEUE_CONNECTION',
            'SESSION_DRIVER',
        ]);

        parent::tearDown();
    }

    public function test_mysql_defaults_are_used_when_connection_is_mysql(): void
    {
        $app = $this->bootApplication([
            'DB_CONNECTION' => 'mysql',
        ]);

        $config = $app->make('config');

        self::assertSame('mysql', $config->get('database.default'));
        self::assertSame('mysql', $config->get('database.connections.mysql.host'));
        self::assertSame('3306', (string) $config->get('database.connections.mysql.port'));
    }

    public function test_pgsql_defaults_are_used_when_connection_is_pgsql(): void
    {
        $app = $this->bootApplication([
            'DB_CONNECTION' => 'pgsql',
        ]);

        $config = $app->make('config');

        self::assertSame('pgsql', $config->get('database.default'));
        self::assertSame('postgres', $config->get('database.connections.pgsql.host'));
        self::assertSame('5432', (string) $config->get('database.connections.pgsql.port'));
    }

    /**
     * @param array<string, string> $variables
     */
    private function bootApplication(array $variables)
    {
        $this->setEnvironment([
            'APP_ENV' => 'testing',
            'CACHE_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ]);

        $this->setEnvironment($variables);

        $app = require __DIR__ . '/../../../bootstrap/app.php';
        $app->make(ConsoleKernel::class)->bootstrap();

        return $app;
    }

    /**
     * @param array<int, string> $keys
     */
    private function clearEnvironment(array $keys): void
    {
        foreach ($keys as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    /**
     * @param array<string, string> $variables
     */
    private function setEnvironment(array $variables): void
    {
        foreach ($variables as $key => $value) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
