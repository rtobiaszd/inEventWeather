<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EventScraperService
{
    private const PRIORITY_CITIES = [
        'São Paulo',
        'Rio de Janeiro',
        'Curitiba',
        'Matinhos',
    ];

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function scrapeAll(): array
    {
        $results = [];

        foreach (self::PRIORITY_CITIES as $city) {
            foreach (['sympla' => 'scrapeSympla', 'eventim' => 'scrapeEventim', 'eventbrite' => 'scrapeEventbrite'] as $provider => $method) {
                try {
                    $count = $this->$method($city);
                    $results[] = ['provider' => $provider, 'city' => $city, 'imported' => $count, 'error' => null];
                } catch (\Throwable $e) {
                    Log::error("[EventScraper] {$provider} / {$city}: {$e->getMessage()}", [
                        'exception' => $e,
                        'provider' => $provider,
                        'city' => $city,
                    ]);
                    $results[] = ['provider' => $provider, 'city' => $city, 'imported' => 0, 'error' => $e->getMessage()];
                }
            }
        }

        return $results;
    }

    public function scrapeSympla(string $city): int
    {
        $imported = 0;
        $page = 1;

        do {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/json',
                    'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                ])
                ->get('https://www.sympla.com.br/api/v1/search', [
                    'q' => $city,
                    'page' => $page,
                    'sort' => 'date',
                    'type' => 'public',
                ]);

            if (!$response->successful()) {
                break;
            }

            $data = $response->json();
            $events = $data['data']['events'] ?? $data['events'] ?? [];

            if (empty($events)) {
                break;
            }

            foreach ($events as $eventData) {
                $eventDate = $eventData['start_date'] ?? $eventData['event_date'] ?? null;
                $eventTime = $eventData['start_time'] ?? $eventData['event_time'] ?? '12:00';

                if (!$eventDate || !$eventData['name'] ?? false) {
                    continue;
                }

                Event::updateOrCreate(
                    [
                        'provider' => 'sympla',
                        'provider_id' => (string) ($eventData['id'] ?? $eventData['slug'] ?? null),
                    ],
                    [
                        'name' => $eventData['name'],
                        'city' => $city,
                        'country' => 'BR',
                        'event_date' => $eventDate,
                        'event_time' => $eventTime,
                        'description' => $eventData['description'] ?? null,
                        'venue' => $eventData['address']['name'] ?? $eventData['venue'] ?? null,
                        'banner_url' => $eventData['image'] ?? $eventData['banner_url'] ?? null,
                        'organizer' => $eventData['organization']['name'] ?? $eventData['organizer'] ?? null,
                        'type' => 'outdoor',
                        'status' => 'planned',
                        'budget' => 0.00,
                        'revenue' => 0.00,
                        'tags' => 'sympla,importado',
                        'created_by' => $this->getAdminUserId(),
                    ]
                );

                $imported++;
            }

            $page++;
            $totalPages = $data['data']['total_pages'] ?? $data['total_pages'] ?? 1;
        } while ($page <= $totalPages);

        return $imported;
    }

    public function scrapeEventim(string $city): int
    {
        Log::info("[EventScraper] Eventim scraping not yet implemented for city: {$city}");

        return 0;
    }

    public function scrapeEventbrite(string $city): int
    {
        Log::info("[EventScraper] Eventbrite scraping not yet implemented for city: {$city}");

        return 0;
    }

    private function getAdminUserId(): ?int
    {
        $admin = User::where('role', 'admin')->where('is_active', true)->first();

        return $admin?->id;
    }
}
