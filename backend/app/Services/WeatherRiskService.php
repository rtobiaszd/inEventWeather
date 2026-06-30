<?php

namespace App\Services;

class WeatherRiskService
{
    private array $alerts    = [];
    private int   $riskScore = 0;

    public function analyze(array $weather, array $forecast = [], int $aqi = 1): array
    {
        $this->alerts    = [];
        $this->riskScore = 0;

        $temp     = (float) ($weather['main']['temp'] ?? 20);
        $humidity = (int)   ($weather['main']['humidity'] ?? 50);
        $windKmh  = round((float) ($weather['wind']['speed'] ?? 0) * 3.6, 1);
        $rainProb = $this->extractRainProbability($forecast);

        $this->checkTemperature($temp);
        $this->checkWind($windKmh);
        $this->checkRain($rainProb);
        $this->checkAirQuality($aqi);
        $this->checkHumidity($humidity);

        $this->riskScore = min(100, $this->riskScore);

        return [
            'score'          => $this->riskScore,
            'status'         => $this->resolveStatus(),
            'recommendation' => $this->buildRecommendation($rainProb),
            'alerts'         => $this->alerts,
            'summary'        => [
                'temperature'      => round($temp, 1),
                'feels_like'       => round((float) ($weather['main']['feels_like'] ?? $temp), 1),
                'humidity'         => $humidity,
                'wind_speed_kmh'   => $windKmh,
                'rain_probability' => $rainProb,
                'aqi'              => $aqi,
            ],
        ];
    }

    private function checkTemperature(float $temp): void
    {
        if ($temp > 38)     $this->addAlert('heat', "Temperatura extrema: {$temp}°C. Risco de insolação.", 'danger', 30);
        elseif ($temp > 32) $this->addAlert('heat', "Temperatura alta: {$temp}°C. Risco de desconforto térmico.", 'warning', 20);
        elseif ($temp < 5)  $this->addAlert('cold', "Temperatura muito baixa: {$temp}°C.", 'danger', 25);
        elseif ($temp < 10) $this->addAlert('cold', "Temperatura baixa: {$temp}°C. Providencie agasalhos.", 'warning', 15);
    }

    private function checkWind(float $windKmh): void
    {
        if ($windKmh > 60)     $this->addAlert('wind', "Vento muito forte: {$windKmh} km/h. Alto risco para estruturas.", 'danger', 40);
        elseif ($windKmh > 35) $this->addAlert('wind', "Vento forte: {$windKmh} km/h. Risco para palcos e tendas.", 'danger', 30);
        elseif ($windKmh > 20) $this->addAlert('wind', "Vento moderado: {$windKmh} km/h. Atenção para estruturas temporárias.", 'warning', 10);
    }

    private function checkRain(int $rainProb): void
    {
        if ($rainProb >= 80)     $this->addAlert('rain', "Chuva muito provável ({$rainProb}%). Recomendado evento indoor.", 'danger', 40);
        elseif ($rainProb >= 60) $this->addAlert('rain', "Alta probabilidade de chuva ({$rainProb}%). Recomendado cobertura.", 'danger', 30);
        elseif ($rainProb >= 40) $this->addAlert('rain', "Probabilidade moderada de chuva ({$rainProb}%). Tenha plano B.", 'warning', 15);
    }

    private function checkAirQuality(int $aqi): void
    {
        if ($aqi >= 5)     $this->addAlert('air', "Qualidade do ar muito ruim (AQI: {$aqi}). Evite atividades ao ar livre.", 'danger', 35);
        elseif ($aqi >= 4) $this->addAlert('air', "Qualidade do ar ruim (AQI: {$aqi}). Grupos sensíveis em alerta.", 'danger', 25);
        elseif ($aqi >= 3) $this->addAlert('air', "Qualidade do ar moderada (AQI: {$aqi}). Atenção para pessoas sensíveis.", 'warning', 10);
    }

    private function checkHumidity(int $humidity): void
    {
        if ($humidity > 90)     $this->addAlert('humidity', "Umidade muito elevada: {$humidity}%. Sensação abafada intensa.", 'warning', 10);
        elseif ($humidity > 80) $this->addAlert('humidity', "Umidade alta: {$humidity}%. Pode causar desconforto.", 'info', 5);
    }

    private function addAlert(string $type, string $message, string $severity, int $score): void
    {
        $this->alerts[]   = ['type' => $type, 'message' => $message, 'severity' => $severity];
        $this->riskScore += $score;
    }

    private function extractRainProbability(array $forecast): int
    {
        if (empty($forecast['list'])) return 0;
        $maxPop = 0;
        foreach (array_slice($forecast['list'], 0, 4) as $item) {
            $maxPop = max($maxPop, (float) ($item['pop'] ?? 0) * 100);
        }
        return (int) round($maxPop);
    }

    private function resolveStatus(): string
    {
        if ($this->riskScore >= 60) return 'HIGH_RISK';
        if ($this->riskScore >= 30) return 'MEDIUM_RISK';
        return 'LOW_RISK';
    }

    private function buildRecommendation(int $rainProb): string
    {
        if ($this->riskScore >= 60) return 'Condições muito adversas. Recomendamos fortemente local fechado ou reagendamento.';
        if ($rainProb >= 60)        return 'Alta probabilidade de chuva. Recomendamos evento indoor ou cobertura total.';
        if ($this->riskScore >= 30) return 'Alguns riscos climáticos. Monitore as previsões e tenha planos alternativos.';
        return 'Condições climáticas favoráveis para eventos. Aproveite!';
    }
}
