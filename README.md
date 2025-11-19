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

See [web/API.md](web/API.md) for detailed documentation.

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
docker compose run --rm k6 run /scripts/100_updates_in_1_sec_test.js  # 100 concurrent updates test
docker compose run --rm k6 run /scripts/max_updates_per_second_test.js  # Maximum throughput test
```

## Performance

- **Update Score**: O(log N)
- **Get Top N**: O(log N + M)
- **Get Rank**: O(log N)

### Load Test Results

**100 Updates in 1 Second Test:**
- Status: ✅ PASS
- Requests handled: 100/100
- Completion time: < 0.3 seconds
- Average latency: 104ms
- P95 latency: 134ms
- Maximum latency: 135ms
- Success rate: 100%

**Maximum Throughput Test (100 concurrent VUs):**
- Requests handled: 937 in 1 second
- Throughput: 937 updates/second
- Average latency: 124ms
- P95 latency: 297ms
- Maximum latency: 419ms
- Success rate: 100%

## Architecture

- **Redis**: Fast leaderboard operations (source of truth)
- **MySQL**: Persistent storage with write-through pattern
- **Recovery**: Automatic Redis rebuild from MySQL on startup
- **Tie-breaking**: Composite score with timestamp for deterministic ordering

## License

MIT License
