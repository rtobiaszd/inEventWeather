<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\EventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private array $allowedFilters = ['date_from', 'date_to', 'status', 'city', 'type'];

    private function applyFilters(Request $request, $query)
    {
        if ($request->date_from) {
            $query->where('event_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('event_date', '<=', $request->date_to);
        }
        if ($request->status) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }
        if ($request->city) {
            $query->where('city', $request->city);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        return $query;
    }

    public function summary(Request $request): JsonResponse
    {
        $query = $this->applyFilters($request, Event::query());

        $summary = (clone $query)
            ->selectRaw('
                COUNT(*) as total_events,
                COALESCE(SUM(budget), 0) as total_budget,
                COALESCE(SUM(revenue), 0) as total_revenue,
                COALESCE(SUM(revenue) - SUM(budget), 0) as total_profit
            ')
            ->first();

        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(budget), 0) as budget, COALESCE(SUM(revenue), 0) as revenue')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $typeCounts = (clone $query)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $avgRoi = (clone $query)
            ->where('budget', '>', 0)
            ->selectRaw('COALESCE(AVG(((revenue - budget) / budget) * 100), 0) as avg_roi')
            ->first()
            ->avg_roi ?? 0;

        return $this->success([
            'summary' => [
                'total_events'  => (int) $summary->total_events,
                'total_budget'  => (float) $summary->total_budget,
                'total_revenue' => (float) $summary->total_revenue,
                'total_profit'  => (float) $summary->total_profit,
                'avg_roi'       => round((float) $avgRoi, 1),
            ],
            'by_status' => [
                'planned'     => ['count' => (int) ($statusCounts['planned']->count ?? 0), 'budget' => (float) ($statusCounts['planned']->budget ?? 0), 'revenue' => (float) ($statusCounts['planned']->revenue ?? 0)],
                'confirmed'   => ['count' => (int) ($statusCounts['confirmed']->count ?? 0), 'budget' => (float) ($statusCounts['confirmed']->budget ?? 0), 'revenue' => (float) ($statusCounts['confirmed']->revenue ?? 0)],
                'in_progress' => ['count' => (int) ($statusCounts['in_progress']->count ?? 0), 'budget' => (float) ($statusCounts['in_progress']->budget ?? 0), 'revenue' => (float) ($statusCounts['in_progress']->revenue ?? 0)],
                'completed'   => ['count' => (int) ($statusCounts['completed']->count ?? 0), 'budget' => (float) ($statusCounts['completed']->budget ?? 0), 'revenue' => (float) ($statusCounts['completed']->revenue ?? 0)],
                'cancelled'   => ['count' => (int) ($statusCounts['cancelled']->count ?? 0), 'budget' => (float) ($statusCounts['cancelled']->budget ?? 0), 'revenue' => (float) ($statusCounts['cancelled']->revenue ?? 0)],
            ],
            'by_type' => [
                'outdoor' => (int) ($typeCounts['outdoor']->count ?? 0),
                'indoor'  => (int) ($typeCounts['indoor']->count ?? 0),
            ],
        ]);
    }

    public function financialTrends(Request $request): JsonResponse
    {
        $months = min((int) ($request->months ?? 12), 60);

        $query = $this->applyFilters($request, Event::query());

        $trends = (clone $query)
            ->selectRaw("
                DATE_FORMAT(event_date, '%Y-%m') as month,
                COUNT(*) as events,
                COALESCE(SUM(budget), 0) as budget,
                COALESCE(SUM(revenue), 0) as revenue,
                COALESCE(SUM(revenue) - SUM(budget), 0) as profit
            ")
            ->where('event_date', '>=', now()->subMonths($months)->startOfMonth())
            ->groupBy(DB::raw("DATE_FORMAT(event_date, '%Y-%m')"))
            ->orderBy('month')
            ->get()
            ->map(function ($row) {
                return [
                    'month'   => $row->month,
                    'events'  => (int) $row->events,
                    'budget'  => (float) $row->budget,
                    'revenue' => (float) $row->revenue,
                    'profit'  => (float) $row->profit,
                ];
            });

        return $this->success([
            'trends' => $trends,
            'months' => $months,
        ]);
    }

    public function cityPerformance(Request $request): JsonResponse
    {
        $query = $this->applyFilters($request, Event::query());

        $cities = (clone $query)
            ->selectRaw("
                city,
                COUNT(*) as events,
                COALESCE(SUM(budget), 0) as total_budget,
                COALESCE(SUM(revenue), 0) as total_revenue,
                COALESCE(SUM(revenue) - SUM(budget), 0) as total_profit,
                COALESCE(AVG(CASE WHEN budget > 0 THEN ((revenue - budget) / budget) * 100 END), 0) as avg_roi
            ")
            ->groupBy('city')
            ->orderByDesc('events')
            ->get()
            ->map(function ($row) {
                return [
                    'city'         => $row->city,
                    'events'       => (int) $row->events,
                    'total_budget' => (float) $row->total_budget,
                    'total_revenue' => (float) $row->total_revenue,
                    'total_profit'  => (float) $row->total_profit,
                    'avg_roi'       => round((float) $row->avg_roi, 1),
                ];
            });

        return $this->success([
            'cities' => $cities,
            'total_cities' => $cities->count(),
        ]);
    }

    public function typePerformance(Request $request): JsonResponse
    {
        $query = $this->applyFilters($request, Event::query());

        $types = (clone $query)
            ->selectRaw("
                type,
                COUNT(*) as events,
                COALESCE(SUM(budget), 0) as total_budget,
                COALESCE(SUM(revenue), 0) as total_revenue,
                COALESCE(SUM(revenue) - SUM(budget), 0) as total_profit,
                COALESCE(AVG(CASE WHEN budget > 0 THEN ((revenue - budget) / budget) * 100 END), 0) as avg_roi
            ")
            ->groupBy('type')
            ->orderByDesc('events')
            ->get()
            ->map(function ($row) {
                return [
                    'type'          => $row->type,
                    'events'        => (int) $row->events,
                    'total_budget'  => (float) $row->total_budget,
                    'total_revenue' => (float) $row->total_revenue,
                    'total_profit'  => (float) $row->total_profit,
                    'avg_roi'       => round((float) $row->avg_roi, 1),
                ];
            });

        return $this->success([
            'types' => $types,
        ]);
    }

    public function sessionAnalytics(Request $request): JsonResponse
    {
        $eventId = $request->event_id;

        $query = EventSession::query();
        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $sessions = (clone $query)
            ->selectRaw("
                event_id,
                COUNT(*) as total_sessions,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed_sessions,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_sessions,
                COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress_sessions,
                COALESCE(SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END), 0) as scheduled_sessions,
                COALESCE(SUM(capacity), 0) as total_capacity,
                COUNT(DISTINCT speaker_name) FILTER (WHERE speaker_name IS NOT NULL AND speaker_name != '') as unique_speakers
            ");

        if ($eventId) {
            $sessions = $sessions->groupBy('event_id')->first();
        } else {
            $sessions = $sessions->groupBy('event_id')->get();
        }

        if ($eventId) {
            return $this->success(['analytics' => $sessions]);
        }

        return $this->success([
            'analytics' => $sessions,
            'total_events_with_sessions' => $sessions ? $sessions->count() : 0,
        ]);
    }

    public function exportCsv(Request $request): \Illuminate\Http\Response
    {
        $query = $this->applyFilters($request, Event::query());
        $events = $query->orderBy('event_date')->get();

        $filename = 'eventos-' . now()->format('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $columns = ['Nome', 'Cidade', 'País', 'Data', 'Horário', 'Tipo', 'Status', 'Local', 'Organizador', 'Contato', 'Público', 'Orçamento', 'Receita', 'Lucro', 'Preço Ingresso', 'Tags'];

        $callback = function () use ($events, $columns) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, $columns, ';');

            foreach ($events as $event) {
                fputcsv($file, [
                    $event->name,
                    $event->city,
                    $event->country,
                    $event->event_date,
                    $event->event_time ? substr($event->event_time, 0, 5) : '',
                    $event->type,
                    $event->status,
                    $event->venue ?? '',
                    $event->organizer ?? '',
                    $event->organizer_contact ?? '',
                    $event->expected_audience ?? 0,
                    number_format((float) ($event->budget ?? 0), 2, ',', '.'),
                    number_format((float) ($event->revenue ?? 0), 2, ',', '.'),
                    number_format((float) (($event->revenue ?? 0) - ($event->budget ?? 0)), 2, ',', '.'),
                    $event->ticket_price ? number_format((float) $event->ticket_price, 2, ',', '.') : '',
                    $event->tags ?? '',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
