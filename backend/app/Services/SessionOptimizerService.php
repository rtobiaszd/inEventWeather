<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Support\Facades\Log;

class SessionOptimizerService
{
    public function __construct(
        private WeatherService $weatherService,
    ) {}

    public function detectConflicts(Event $event): array
    {
        $sessions = EventSession::where('event_id', $event->id)
            ->with('speakers')
            ->where('status', '!=', 'cancelled')
            ->get();

        $conflicts = [];
        $rooms = [];
        $speakerTimes = [];

        foreach ($sessions as $session) {
            $key = $session->date . '|' . $session->room;

            foreach ($sessions as $other) {
                if ($other->id === $session->id || $other->date !== $session->date) {
                    continue;
                }

                if ($other->room && $other->room === $session->room && $this->timesOverlap($session, $other)) {
                    $conflicts[] = [
                        'type' => 'room',
                        'session_a' => ['id' => $session->id, 'name' => $session->name, 'room' => $session->room],
                        'session_b' => ['id' => $other->id, 'name' => $other->name, 'room' => $other->room],
                        'date' => $session->date,
                        'message' => "Sala '{$session->room}' duplicada na data {$session->date}",
                    ];
                }
            }

            foreach ($session->speakers as $speaker) {
                foreach ($sessions as $other) {
                    if ($other->id === $session->id || $other->date !== $session->date) {
                        continue;
                    }

                    if ($other->speakers->contains('id', $speaker->id) && $this->timesOverlap($session, $other)) {
                        $conflicts[] = [
                            'type' => 'speaker',
                            'speaker' => ['id' => $speaker->id, 'name' => $speaker->name],
                            'session_a' => ['id' => $session->id, 'name' => $session->name],
                            'session_b' => ['id' => $other->id, 'name' => $other->name],
                            'date' => $session->date,
                            'message' => "Palestrante '{$speaker->name}' em duas sessões no mesmo horário em {$session->date}",
                        ];
                    }
                }
            }
        }

        return $conflicts;
    }

    public function suggestOptimalSchedule(Event $event): array
    {
        $sessions = EventSession::where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $suggestions = [];

        try {
            $weather = $this->weatherService->getForecast($event->city, $event->country);
            $current = $this->weatherService->getCurrentWeather($event->city, $event->country);
        } catch (\Throwable $e) {
            Log::warning("[SessionOptimizer] Weather unavailable for {$event->city}: {$e->getMessage()}");

            return [];
        }

        $hourlyData = $weather['list'] ?? [];

        foreach ($sessions as $session) {
            $originalStart = $session->start_time;
            $originalEnd = $session->end_time;

            $suggestion = [
                'session_id' => $session->id,
                'session_name' => $session->name,
                'date' => (string) $session->date,
                'original' => [
                    'start_time' => $originalStart,
                    'end_time' => $originalEnd,
                    'outdoor_suitable' => $session->outdoor_suitable,
                ],
                'suggested' => null,
                'risk_at_original' => null,
                'reason' => null,
            ];

            $originalRisk = $this->assessTimeSlotRisk($session->date, $originalStart, $hourlyData);

            $suggestion['risk_at_original'] = $originalRisk;

            if ($session->outdoor_suitable && $originalRisk && $originalRisk['level'] === 'HIGH_RISK') {
                $bestSlot = $this->findBestTimeSlot($session->date, $originalStart, $originalEnd, $hourlyData, $sessions);

                if ($bestSlot && ($bestSlot['start_time'] !== $originalStart || $bestSlot['end_time'] !== $originalEnd)) {
                    $suggestion['suggested'] = [
                        'start_time' => $bestSlot['start_time'],
                        'end_time' => $bestSlot['end_time'],
                        'risk' => $bestSlot['risk'],
                    ];
                    $suggestion['reason'] = 'Risco climático alto no horário original. Horário alternativo sugerido com menor probabilidade de chuva.';
                }
            }

            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    public function applyWeatherOptimization(Event $event): int
    {
        $suggestions = $this->suggestOptimalSchedule($event);
        $applied = 0;

        foreach ($suggestions as $suggestion) {
            if (!$suggestion['suggested']) {
                continue;
            }

            EventSession::where('id', $suggestion['session_id'])->update([
                'actual_start_time' => $suggestion['suggested']['start_time'],
                'actual_end_time' => $suggestion['suggested']['end_time'],
                'weather_optimized_at' => now(),
            ]);

            $applied++;
        }

        return $applied;
    }

    private function timesOverlap(EventSession $a, EventSession $b): bool
    {
        if ($a->start_time < $b->end_time && $b->start_time < $a->end_time) {
            return true;
        }

        return false;
    }

    private function assessTimeSlotRisk(string $date, string $time, array $hourlyData): ?array
    {
        $targetDate = $date . ' ' . $time;

        $closest = null;
        $minDiff = PHP_INT_MAX;

        foreach ($hourlyData as $item) {
            $dt = $item['dt'] ?? 0;
            $diff = abs($dt - strtotime($targetDate));

            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $item;
            }
        }

        if (!$closest) {
            return null;
        }

        $rain = $closest['rain']['3h'] ?? 0;
        $pop = ($closest['pop'] ?? 0) * 100;
        $temp = $closest['main']['temp'] ?? 25;
        $wind = $closest['wind']['speed'] ?? 0;
        $weatherMain = $closest['weather'][0]['main'] ?? 'Clear';

        $isStorm = $weatherMain === 'Thunderstorm';
        $isHeavyRain = $weatherMain === 'Rain' && $pop > 60;
        $isHeat = $temp > 35;
        $isWindy = $wind > 10;

        $score = 0;
        $score += $isStorm ? 40 : ($isHeavyRain ? 25 : ($pop > 50 ? 15 : 0));
        $score += $isHeat ? 20 : 0;
        $score += $isWindy ? 10 : 0;

        $level = $score >= 40 ? 'HIGH_RISK' : ($score >= 15 ? 'MEDIUM_RISK' : 'LOW_RISK');

        return [
            'score' => $score,
            'level' => $level,
            'rain_probability' => round($pop, 0),
            'temperature' => round($temp, 1),
            'wind_speed' => round($wind, 1),
            'condition' => $weatherMain,
        ];
    }

    private function findBestTimeSlot(string $date, string $originalStart, string $originalEnd, array $hourlyData, iterable $existingSessions): ?array
    {
        $usedRanges = [];
        foreach ($existingSessions as $s) {
            if ((string) $s->date === $date) {
                $usedRanges[] = ['start' => $s->start_time, 'end' => $s->end_time];
            }
        }

        $originalHour = (int) explode(':', $originalStart)[0];
        $candidates = [];

        for ($hour = max(7, $originalHour - 3); $hour <= min(21, $originalHour + 3); $hour++) {
            $start = sprintf('%02d:00', $hour);
            $end = sprintf('%02d:00', $hour + 1);

            $conflicts = false;
            foreach ($usedRanges as $range) {
                if ($start < $range['end'] && $range['start'] < $end) {
                    $conflicts = true;
                    break;
                }
            }

            if ($conflicts) {
                continue;
            }

            $risk = $this->assessTimeSlotRisk($date, $start, $hourlyData);

            if ($risk) {
                $candidates[] = [
                    'start_time' => $start,
                    'end_time' => $end,
                    'risk' => $risk,
                    'score' => $risk['score'],
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $a['score'] <=> $b['score']);

        $best = $candidates[0];

        if ($best['score'] >= 40) {
            return null;
        }

        return $best;
    }
}
