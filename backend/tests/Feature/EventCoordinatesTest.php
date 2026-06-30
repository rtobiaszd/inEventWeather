<?php

namespace Tests\Feature;

use App\Http\Controllers\EventController;
use App\Models\Event;
use App\Services\WeatherService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

final class EventCoordinatesTest extends TestCase
{
    private mixed $app;
    private Builder $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = $this->bootApplication();
        $this->configureSqliteDatabase();
        $this->createEventsTable();
    }

    protected function tearDown(): void
    {
        if ($this->schema->hasTable('events')) {
            $this->schema->drop('events');
        }

        $this->clearEnvironment([
            'APP_ENV',
            'CACHE_DRIVER',
            'APP_CONFIG_CACHE',
            'DB_CONNECTION',
            'DB_DATABASE',
            'QUEUE_CONNECTION',
            'SESSION_DRIVER',
        ]);

        parent::tearDown();
    }

    public function test_store_persists_explicit_coordinates(): void
    {
        $weatherService = $this->createMock(WeatherService::class);
        $controller = new EventController($weatherService);

        $request = Request::create('/api/events', 'POST', [
            'name' => 'Festival com ponto fixo',
            'city' => 'Sao Paulo',
            'country' => 'BR',
            'latitude' => -23.55052,
            'longitude' => -46.633308,
            'event_date' => '2026-08-15',
            'event_time' => '18:00',
            'type' => 'outdoor',
            'expected_audience' => 5000,
        ]);
        $response = $controller->store($request);
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertSame(-23.55052, (float) $payload['data']['latitude']);
        self::assertSame(-46.633308, (float) $payload['data']['longitude']);
    }

    public function test_store_geocodes_coordinates_when_not_informed(): void
    {
        $weatherService = $this->createMock(WeatherService::class);
        $weatherService->expects(self::once())
            ->method('geocode')
            ->with('Campinas', 'BR')
            ->willReturn([
                'lat' => -22.90556,
                'lon' => -47.06083,
                'name' => 'Campinas',
            ]);

        $controller = new EventController($weatherService);

        $request = Request::create('/api/events', 'POST', [
            'name' => 'Hackathon 2026',
            'city' => 'Campinas',
            'country' => 'BR',
            'event_date' => '2026-10-10',
            'event_time' => '09:00',
            'type' => 'indoor',
        ]);
        $response = $controller->store($request);
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(-22.90556, (float) $payload['data']['latitude']);
        self::assertSame(-47.06083, (float) $payload['data']['longitude']);
    }

    public function test_index_backfills_coordinates_for_old_events(): void
    {
        Event::query()->create([
            'name' => 'Evento legado',
            'city' => 'Curitiba',
            'country' => 'BR',
            'event_date' => '2026-11-01',
            'event_time' => '20:00:00',
            'type' => 'outdoor',
            'expected_audience' => 1000,
            'description' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        $weatherService = $this->createMock(WeatherService::class);
        $weatherService->expects(self::once())
            ->method('geocode')
            ->with('Curitiba', 'BR')
            ->willReturn([
                'lat' => -25.4284,
                'lon' => -49.2733,
                'name' => 'Curitiba',
            ]);

        $controller = new EventController($weatherService);
        $response = $controller->index();
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(-25.4284, (float) $payload['data'][0]['latitude']);
        self::assertSame(-49.2733, (float) $payload['data'][0]['longitude']);

        $event = Event::query()->firstOrFail();
        self::assertSame(-25.4284, (float) $event->latitude);
        self::assertSame(-49.2733, (float) $event->longitude);
    }

    private function bootApplication()
    {
        Env::enablePutenv();

        $this->setEnvironment([
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => __DIR__ . '/../../bootstrap/cache/testing-config.php',
            'CACHE_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ]);

        $app = require __DIR__ . '/../../bootstrap/app.php';
        $app->make(ConsoleKernel::class)->bootstrap();
        restore_error_handler();
        restore_exception_handler();

        return $app;
    }

    private function configureSqliteDatabase(): void
    {
        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite');
        $config->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $this->app->make('db')->purge('sqlite');
        $this->app->make('db')->reconnect('sqlite');
        $this->schema = $this->app->make('db')->connection('sqlite')->getSchemaBuilder();
    }

    private function createEventsTable(): void
    {
        $this->schema->create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city', 100);
            $table->string('country', 10)->default('BR');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('event_date');
            $table->time('event_time');
            $table->string('type', 50);
            $table->unsignedInteger('expected_audience')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
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
