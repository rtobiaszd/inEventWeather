# inEvent — Event Weather Dashboard

Sistema para gerenciamento de eventos com análise climática em tempo real. Ao cadastrar um evento com cidade e data, o sistema consulta automaticamente o clima atual, gera uma **previsão para o dia do evento** e calcula um **índice de risco climático** (temperatura, vento, chuva, qualidade do ar). O dashboard também usa um **mapa Leaflet** como fundo do card principal de clima, posicionando os eventos pelas coordenadas geográficas.

(Demo)
https://inevent-front.onrender.com/
-usuário: admin
-senha:   admin123
## Stack

| Camada    | Tecnologia                          |
|-----------|-------------------------------------|
| Backend   | **Laravel 11** (PHP 8.2), Sanctum   |
| ORM       | Eloquent + migrations               |
| Banco     | **MySQL 8.0** ou **PostgreSQL 16** (selecionável via `.env`) |
| Cache     | **Redis 7** (TTL: geo 24h, atual 10min, previsão 30min) |
| Frontend  | **Vue 3** + Vite + Leaflet          |
| Auth      | Bearer token (24h), `personal_access_tokens` |
| Clima     | OpenWeather API (Current, Forecast, Air Pollution) |
| Container | Docker Compose (6 serviços)         |

## Pré-requisitos

- Docker Desktop
- Chave da [OpenWeather API](https://openweathermap.org/api) (gratuita)

## Configuração e execução

```bash
# 1. Clone o projeto
git clone <repo-url> inEvent
cd inEvent

# 2. Configure as variáveis de ambiente
cp .env.example .env
# Edite .env e preencha OPENWEATHER_API_KEY
# Para trocar entre MySQL e PostgreSQL, altere apenas DB_CONNECTION

# 3. Suba os containers (build + migrate + seed automáticos)
docker compose up -d --build

# 4. Acesse
#   Frontend:  http://localhost:5173
#   API:       http://localhost:8080/api
#   Swagger:   http://localhost:8080/docs
```

### Credenciais padrão (seed)

| Campo    | Valor      |
|----------|------------|
| Usuário  | `admin`    |
| Senha    | `admin123` |

## Variáveis de ambiente (`.env`)

```env
OPENWEATHER_API_KEY=sua_chave_aqui
DB_CONNECTION=mysql
DB_ROOT_PASSWORD=rootpassword
DB_NAME=event_weather
DB_USER=weather_user
DB_PASSWORD=weather_pass
APP_ENV=development
```

> A chave da OpenWeather fica **exclusivamente no container PHP** — o frontend nunca a acessa diretamente.
> `DB_CONNECTION=mysql` usa o MySQL do `docker-compose.yml`; `DB_CONNECTION=pgsql` usa o PostgreSQL.

## Arquitetura

```
inEvent/
├── backend/               # Laravel 11 (API REST + Sanctum)
│   ├── app/
│   │   ├── Http/Controllers/   # Auth, Event, Weather, History, Favorite
│   │   ├── Models/             # User, Event, WeatherHistory, FavoriteCity
│   │   └── Services/           # WeatherService, WeatherRiskService
│   ├── database/
│   │   ├── migrations/         # Schema versionado
│   │   └── seeders/            # Dados iniciais idempotentes
│   ├── routes/api.php
│   └── public/
│       ├── openapi.json        # Spec OpenAPI 3.0
│       └── docs/index.html     # Swagger UI
├── frontend/              # Vue 3 + Vite
│   └── src/
│       ├── pages/         # Login, Dashboard, Events, EventsMap, Weather, History
│       ├── components/    # Sidebar, EventCard, WeatherCard...
│       ├── composables/   # useAuth.js (singleton)
│       └── services/api.js
├── database/
│   └── database.sql       # Bootstrap do banco (charset/collation)
├── docker/
│   └── entrypoint.sh      # wait-for-db → migrate → seed → php-fpm
├── nginx/nginx.conf
└── docker-compose.yml
```

## API — Endpoints principais

Documentação completa: **`http://localhost:8080/docs`**

| Método | Rota                   | Auth | Descrição                                |
|--------|------------------------|------|------------------------------------------|
| POST   | `/api/auth/login`      | —    | Login, retorna Bearer token              |
| POST   | `/api/auth/register`   | —    | Cadastro de novo usuário                 |
| POST   | `/api/auth/logout`     | ✓    | Invalida o token atual                   |
| GET    | `/api/auth/me`         | ✓    | Dados do usuário autenticado             |
| GET    | `/api/events`          | ✓    | Lista eventos ordenados por data         |
| POST   | `/api/events`          | ✓    | Cria evento                              |
| GET    | `/api/events/{id}`     | ✓    | Detalhe + clima atual + previsão + risco |
| PUT    | `/api/events/{id}`     | ✓    | Atualiza evento                          |
| DELETE | `/api/events/{id}`     | ✓    | Remove evento                            |
| GET    | `/api/weather/search`  | ✓    | Clima atual (salva no histórico)         |
| GET    | `/api/weather/forecast`| ✓    | Previsão 5 dias / 3h                     |
| GET    | `/api/history`         | ✓    | Histórico de consultas                   |
| GET    | `/api/weather/air-quality`| ✓ | Qualidade do ar (AQI + componentes)      |
| GET    | `/api/weather/best-dates`| ✓ | Recomenda melhores datas para evento outdoor |
| GET    | `/api/events/risk-alerts`| ✓ | Alertas consolidados de risco climático  |
| GET    | `/api/events/financial-insights`| ✓ | Inteligência financeira + risco climático |
| GET    | `/api/history`         | ✓    | Histórico de consultas                   |
| GET    | `/api/favorites`       | ✓    | Cidades favoritas com eventos embutidos  |
| POST   | `/api/favorites`       | ✓    | Adiciona cidade (country opcional, padrão BR) |
| DELETE | `/api/favorites/{id}`  | ✓    | Remove cidade dos favoritos              |

## Mapa interativo de eventos (`/events/map`)

Página dedicada com mapa **Leaflet** full-page que exibe todos os eventos cadastrados:

- **Marcadores** — cada evento com coordenadas aparece como um ponto no mapa
- **Tooltip** — ao passar o mouse, mostra nome, data, horário e cidade do evento
- **Clique** — abre um card com detalhes e link para editar
- **Duplo clique** — cria um novo evento rapidamente na posição clicada (modal com nome, data, cidade)
- **Busca de endereço** — campo de busca que consulta a Nominatim (OpenStreetMap) e centraliza o mapa no local
- **Filtro por cidade** — ao clicar "Ver mapa" nos favoritos, o mapa filtra apenas eventos daquela cidade

### Acesso

- **Dashboard** — o badge "N eventos no mapa" no card de clima é um link para o mapa
- **Favoritos** — cada card de cidade tem o botão "Ver mapa" que abre o mapa filtrado pela cidade
- **Sidebar** — link "Mapa" no menu de navegação

### Exemplo de payload de evento com coordenadas

```json
{
  "name": "Festival de Verão",
  "city": "São Paulo",
  "country": "BR",
  "latitude": -23.55052,
  "longitude": -46.633308,
  "event_date": "2026-08-15",
  "event_time": "18:00",
  "type": "outdoor",
  "expected_audience": 5000,
  "description": "Evento ao ar livre no centro da cidade"
}
```

### Cidades favoritas com eventos (`GET /api/favorites`)

Cada item do array retornado inclui um campo `events` com os próximos eventos cadastrados naquela cidade (consulta em 2 queries totais):

```json
{
  "id": 1,
  "city": "São Paulo",
  "country": "BR",
  "events": [
    {
      "id": 3,
      "name": "Festival de Verão",
      "city": "São Paulo",
      "event_date": "2026-08-15",
      "event_time": "18:00:00",
      "type": "outdoor"
    }
  ]
}
```

### Auto-favoritar cidade ao criar evento

Ao criar um evento via `POST /api/events`, o backend automaticamente adiciona a cidade aos favoritos (operação idempotente — ignora silenciosamente se já existir).

### Coordenadas automáticas dos eventos

- Se `latitude` e `longitude` forem enviadas, elas são persistidas e usadas como fonte primária do mapa.
- Se as coordenadas forem omitidas, a API tenta resolver a posição pelo geocoding da OpenWeather.
- Eventos antigos já salvos sem coordenadas são atualizados automaticamente ao passar por `GET /api/events` ou `GET /api/events/{id}`.

### Previsão climática no evento (`event_forecast`)

Ao chamar `GET /api/events/{id}`, o campo `event_forecast` é calculado automaticamente:

- **`type: "forecast"`** — evento nos próximos 5 dias: entrada real mais próxima do horário do evento
- **`type: "estimate"`** — evento além de 5 dias ou no passado: média das próximas 120h na cidade

```json
"event_forecast": {
  "available": true,
  "type": "forecast",
  "note": "Previsão baseada em dados reais para o horário do evento",
  "temperature": 27.3,
  "feels_like": 29.1,
  "humidity": 65,
  "wind_speed": 4.2,
  "rain_probability": 20,
  "weather_main": "Clear",
  "weather_description": "céu limpo",
  "icon": "01d",
  "forecast_time": "2026-08-15 18:00:00"
}
```

### Índice de risco (`risk`)

```json
"risk": {
  "score": 15,
  "status": "LOW_RISK",
  "recommendation": "Condições climáticas favoráveis para eventos.",
  "alerts": [],
  "summary": {
    "temperature": 27.3,
    "humidity": 65,
    "wind_speed_kmh": 15.1,
    "rain_probability": 20,
    "aqi": 1
  }
}
```

### Resposta do clima com coordenadas

`GET /api/weather/search` passou a retornar também as coordenadas do ponto consultado:

```json
{
  "city": "São Paulo",
  "country": "BR",
  "coordinates": {
    "latitude": -23.5505,
    "longitude": -46.6333
  },
  "current": {
    "temperature": 25.2,
    "feels_like": 25.3
  }
}
```

| Status        | Score   |
|---------------|---------|
| `LOW_RISK`    | 0–29    |
| `MEDIUM_RISK` | 30–59   |
| `HIGH_RISK`   | 60–100  |

## Comandos úteis

```bash
# Logs do backend
docker logs event-weather-php -f

# Acessar shell do container PHP
docker exec -it event-weather-php sh

# Rodar migrations manualmente
docker exec -it event-weather-php php artisan migrate

# Rodar seeders manualmente
docker exec -it event-weather-php php artisan db:seed

# Rodar testes automatizados do backend
docker exec -it event-weather-php vendor/bin/phpunit --configuration /var/www/html/phpunit.xml

# Gerar nova APP_KEY
docker exec -it event-weather-php php artisan key:generate

# Rebuild completo (limpa volumes)
docker compose down -v && docker compose up -d --build
```

## Testes

O backend cobre agora os cenários centrais das novas features geoespaciais:

- persistência de `latitude` e `longitude` quando enviadas manualmente
- geocodificação automática ao criar evento sem coordenadas
- backfill automático de coordenadas em eventos legados ao listar a API

## Cache (Redis)

Todas as chamadas à OpenWeather passam por cache Redis no backend:

| Tipo         | TTL     | Chave                        |
|--------------|---------|------------------------------|
| Geocoding    | 24h     | `ew:geo:{cidade}:{país}`     |
| Clima atual  | 10 min  | `ew:current:{cidade}:{país}` |
| Previsão 5d  | 30 min  | `ew:forecast:{cidade}:{país}`|
| Qualidade ar | 30 min  | `ew:air:{cidade}:{país}`     |

Resultado: latência cai de ~4600ms (cold) para ~10ms (warm).

## Containers

| Container               | Porta | Descrição             |
|-------------------------|-------|-----------------------|
| `event-weather-mysql`   | 3306  | MySQL 8.0             |
| `event-weather-postgres`| 5432  | PostgreSQL 16         |
| `event-weather-redis`   | —     | Redis 7 (cache)       |
| `event-weather-php`     | —     | Laravel + php-fpm     |
| `event-weather-nginx`   | 8080  | Proxy reverso         |
| `event-weather-frontend`| 5173  | Vue 3 + Vite (HMR)   |


## Smart Risk Intelligence

Funcionalidades de inteligência climática implementadas em **30/06/2026**:

### Alertas Inteligentes (`/api/events/risk-alerts`)

Endpoint que analisa todos os eventos dos próximos 14 dias e retorna:
- Score e status de risco (HIGH_RISK, MEDIUM_RISK, LOW_RISK) por evento
- Alertas específicos (calor, vento, chuva, qualidade do ar)
- Previsão climática para o dia/hora de cada evento
- Ações recomendadas baseadas no nível de risco

Exibido no **Dashboard** como um painel consolidado com:
- Cards coloridos por severidade (vermelho = alto risco, amarelo = médio)
- Badge de alerta no **sidebar** com contagem total
- Link direto para edição de cada evento em risco

### Recomendador de Data Ideal (`/api/weather/best-dates`)

Endpoint que analisa a previsão dos próximos 14 dias para uma cidade e retorna:
- Score de 0 a 100 para cada data (menor = melhor)
- Classificação: IDEAL, FAVORABLE, CAUTION, AVOID
- Métricas detalhadas: temperatura, chuva máxima, vento máximo, umidade
- Condição predominante (limpo, nublado, chuvoso, etc.)
- Melhor data destacada automaticamente

Página dedicada em `/events/best-dates` com:
- Cards visuais com score ring para cada dia (com animação)
- Destaque colorido da melhor data
- Ordenação da melhor para a pior data
- **Personalização de pesos**: sliders para ajustar importância de chuva, vento, temperatura e umidade — o ranking é recalculado instantaneamente no frontend
- **Gráfico de tendência climática**: SVG interativo com linha de temperatura, barras de chuva e zonas de risco ao longo dos 14 dias
- **Botão "Criar Evento"** em cada card e no destaque — redireciona para `/events/create?city=...&date=...` com dados pré-preenchidos
- **Acesso rápido a cidades favoritas**: botões na horizontal para trocar de cidade instantaneamente
- **Auto-search** via query param `?city=` na URL
- **Cidades populares** sugeridas no empty state

## Financial Weather Intelligence (`/api/events/financial-insights`)

Implementado em **30/06/2026** — cruza dados financeiros dos eventos com análise de risco climático para gerar inteligência de negócio.

### O que entrega

- **Capital em Risco**: soma dos orçamentos de eventos classificados como `HIGH_RISK`
- **ROI Ajustado pelo Clima**: receita projetada com desconto baseado no score de risco
- **Exposição Financeira**: `budget × (risk_score / 100)` — quanto do orçamento está comprometido
- **Receita Ajustada**: `revenue × (1 - risk_score / 200)` — projeção realista de receita
- **Matriz Risco × Orçamento**: gráfico de barras mostrando distribuição do orçamento por nível de risco
- **Portfólio**: total de eventos, orçamento total, receita total, lucro/prejuízo, ROI médio

### Como funciona

O backend percorre todos os eventos, consulta o clima atual de cada cidade via OpenWeather (com cache Redis), calcula o risk score via `WeatherRiskService` e computa os indicadores financeiros.

Nenhum campo novo em banco — tudo é calculado em tempo real com dados já existentes.

### Exibição no Dashboard

Um novo painel **"Inteligência Financeira"** aparece no Dashboard quando há eventos cadastrados, exibindo:

- Cards com métricas-chave (eventos, orçamento, receita, lucro, ROI, capital em risco)
- Barra de distribuição do orçamento por nível de risco (LOW / MEDIUM / HIGH)
- Botão para atualização manual dos dados

### Exemplo de resposta da API

```json
{
  "success": true,
  "data": {
    "summary": {
      "total_events": 10,
      "total_budget": 250000.00,
      "total_revenue": 320000.00,
      "total_profit": 70000.00,
      "avg_roi": 28.0,
      "capital_at_risk": 85000.00,
      "high_risk_financial": 3,
      "high_risk_count": 3,
      "medium_risk_count": 2,
      "profitable_count": 7
    },
    "events": [
      {
        "event_id": 1,
        "event_name": "Festival de Verão",
        "budget": 50000.00,
        "revenue": 80000.00,
        "profit": 30000.00,
        "roi": 60.0,
        "risk_status": "LOW_RISK",
        "risk_score": 15,
        "financial_exposure": 7500.00,
        "adjusted_revenue": 74000.00
      }
    ],
    "distribution": {
      "by_risk": { "LOW_RISK": 5, "MEDIUM_RISK": 2, "HIGH_RISK": 3, "unknown": 0 },
      "budget_at_risk": { "LOW_RISK": 100000, "MEDIUM_RISK": 65000, "HIGH_RISK": 85000, "unknown": 0 }
    }
  }
}
