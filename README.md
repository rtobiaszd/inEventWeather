# inEvent — Event Weather Dashboard

Sistema para gerenciamento de eventos com análise climática em tempo real. Ao cadastrar um evento com cidade e data, o sistema consulta automaticamente o clima atual, gera uma **previsão para o dia do evento** e calcula um **índice de risco climático** (temperatura, vento, chuva, qualidade do ar).

## Stack

| Camada    | Tecnologia                          |
|-----------|-------------------------------------|
| Backend   | **Laravel 11** (PHP 8.2), Sanctum   |
| ORM       | Eloquent + migrations               |
| Banco     | **MySQL 8.0** ou **PostgreSQL 16** (selecionável via `.env`) |
| Cache     | **Redis 7** (TTL: geo 24h, atual 10min, previsão 30min) |
| Frontend  | **Vue 3** + Vite + Tailwind CSS     |
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
│       ├── pages/         # Login, Dashboard, Events, Weather, History
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
| GET    | `/api/history`         | ✓    | Histórico de consultas                   |
| GET    | `/api/favorites`       | ✓    | Cidades favoritas com eventos embutidos  |
| POST   | `/api/favorites`       | ✓    | Adiciona cidade (country opcional, padrão BR) |
| DELETE | `/api/favorites/{id}`  | ✓    | Remove cidade dos favoritos              |

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

# Gerar nova APP_KEY
docker exec -it event-weather-php php artisan key:generate

# Rebuild completo (limpa volumes)
docker compose down -v && docker compose up -d --build
```

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
