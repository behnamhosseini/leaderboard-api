# Real-Time Player Ladder System

High-performance leaderboard service for online games built with PHP, Redis, and MySQL.

## Features

- Real-time score updates with instant rank recalculation
- Get top N players and individual player rank
- Handles 100+ updates/second
- Data persistence with MySQL
- Docker support

## Tech Stack

- PHP 8.3-FPM with Redis Sorted Sets (O(log N) operations)
- Nginx as reverse proxy
- MySQL for persistence
- Slim Framework for REST API

## Quick Start

```bash
# Clone and setup
git clone <repository-url>
cd leaderboard-api
cp web/.env.example web/.env

# Start services (PHP-FPM + Nginx)
docker compose up -d

# Install dependencies
docker compose exec app composer install
```

## API Endpoints

See [API.md](API.md) for detailed documentation.

- `POST /api/players/{playerId}/score` - Update player score
- `GET /api/leaderboard/top?limit=N` - Get top N players
- `GET /api/players/{playerId}/rank` - Get player rank
- `GET /api/leaderboard/count` - Get total players count
- `POST /api/players/batch` - Update multiple players (batch)

## Testing

```bash
# Unit and integration tests
docker compose exec app composer test

# Code coverage
docker compose exec app composer test-coverage

# Static analysis
docker compose exec app composer analyse

# Load testing with K6
docker compose run --rm k6 run /scripts/requirements_test.js  # 1000 VU test
docker compose run --rm k6 run /scripts/load_test.js          # Stress test (100-1000 VU)
```

## Performance

- **Update Score**: O(log N)
- **Get Top N**: O(log N + M)
- **Get Rank**: O(log N)
- **Response times**: <100ms (P95)
- **Throughput**: 100+ updates/second

## Architecture

- **Redis**: Fast leaderboard operations (source of truth)
- **MySQL**: Persistent storage with write-through pattern
- **Recovery**: Automatic Redis rebuild from MySQL on startup
- **Tie-breaking**: Composite score with timestamp for deterministic ordering

## License

MIT License
