<?php

namespace App\Console\Commands;

use App\Services\EventScraperService;
use Illuminate\Console\Command;

class ScrapePopularEvents extends Command
{
    protected $signature = 'inevent:scrape';
    protected $description = 'Executa a raspagem de eventos das plataformas Sympla, Eventim e Eventbrite';

    public function __construct(
        private EventScraperService $scraper,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Iniciando raspagem de eventos...');
        $this->newLine();

        $results = $this->scraper->scrapeAll();

        $total = 0;

        foreach ($results as $result) {
            $icon = $result['error'] ? '✗' : '✓';
            $msg = "{$icon} [{$result['provider']}] {$result['city']}: {$result['imported']} eventos importados";

            if ($result['error']) {
                $this->error("{$msg} — Erro: {$result['error']}");
            } else {
                $this->info($msg);
            }

            $total += $result['imported'];
        }

        $this->newLine();
        $this->info("Raspagem concluída. Total de {$total} novos eventos importados.");

        return Command::SUCCESS;
    }
}
