# Real-Time Player Ladder System

A high-performance, real-time leaderboard service for online games built with PHP, Redis, and MySQL.

## Features

- ✅ Real-time score updates with instant rank recalculation
- ✅ Get top N players efficiently
- ✅ Get individual player rank
- ✅ High performance (handles 100+ updates/second)
- ✅ Data persistence with MySQL
- ✅ RESTful API
- ✅ Docker support
- ✅ Comprehensive test coverage (TDD approach)

## Architecture

### Technology Stack

- **PHP 8.3+**: Modern PHP with type hints
- **Redis**: Sorted sets for O(log N) leaderboard operations
- **MySQL**: Persistent storage for player data
- **Slim Framework**: Lightweight REST API
- **PHPUnit**: Unit and integration testing

### Design Decisions

1. **Redis Sorted Sets**: Used for the leaderboard to achieve O(log N) complexity for insertions and rank queries
2. **Hybrid Storage**: Redis for fast reads/writes, MySQL for durability
3. **Clean Architecture**: Separation of concerns with interfaces and dependency injection
4. **TDD Approach**: Tests written before implementation

## Installation

### Prerequisites

- PHP 8.3 or higher
- Composer
- Redis
- MySQL 8.0+
- Docker & Docker Compose (optional)

### Using Docker (Recommended)

```bash
# Clone the repository
git clone <repository-url>
cd leaderboard-service

# Copy environment file
cp .env.example .env

# Start services
docker compose up -d

# Install dependencies
docker compose exec app composer install

# Run database migrations
docker compose exec mysql mysql -uroot -proot leaderboard_db < database/schema.sql
```

### Manual Installation

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Update .env with your database and Redis credentials

# Create database
mysql -uroot -p < database/schema.sql

# Start PHP built-in server
php -S localhost:8000 -t public
```

## API Endpoints

### Update Player Score

```http
POST /api/players/{playerId}/score
Content-Type: application/json

{
  "score": 1500
}
```

**Response:**
```json
{
  "message": "Score updated successfully",
  "player_id": "player123",
  "score": 1500
}
```

### Get Top Players

```http
GET /api/leaderboard/top?limit=10
```

**Response:**
```json
{
  "players": [
    {
      "player_id": "player1",
      "score": 3000,
      "rank": 1
    },
    {
      "player_id": "player2",
      "score": 2500,
      "rank": 2
    }
  ],
  "total": 10
}
```

### Get Player Rank

```http
GET /api/players/{playerId}/rank
```

**Response:**
```json
{
  "player_id": "player123",
  "score": 1500,
  "rank": 5
}
```

### Get Total Players Count

```http
GET /api/leaderboard/count
```

**Response:**
```json
{
  "total_players": 150
}
```

## Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run static analysis
composer analyse
```

## Simulation Scripts

### Basic Simulation

Run the simulation script to see the system in action:

```bash
php scripts/simulation.php
```

This script:
- Creates multiple players with initial scores
- Simulates score updates over multiple rounds
- Shows real-time rank changes
- Displays final leaderboard

### Performance Test

#### Using PHP Script

Test the system's ability to handle 100+ updates per second:

```bash
php scripts/performance_test.php
```

This script:
- Initializes 100 players
- Performs 150 updates/second for 5 seconds
- Tests query performance (getTopPlayers, getPlayerRank)
- Verifies response times are under 100ms

#### Using K6 Load Testing

For more comprehensive load testing with K6:

```bash
# Run requirements test (1000 concurrent users for 1.5 minutes)
docker compose run --rm k6 run /scripts/requirements_test.js

# Run full load test (scales from 100 to 1000 users)
docker compose run --rm k6 run /scripts/load_test.js

# Run with custom base URL
docker compose run --rm -e BASE_URL=http://app:8000 k6 run /scripts/load_test.js
```

K6 tests verify:
- Response times < 100ms (P95)
- Real-time rank updates
- System stability under high load
- Error rates < 1%

## Performance

- **Update Score**: O(log N) - Redis sorted set insertion
- **Get Top N**: O(log N + M) - M is the number of players requested
- **Get Player Rank**: O(log N) - Redis rank query
- **Concurrent Updates**: Thread-safe with Redis atomic operations

The system is designed to handle:
- 100+ score updates per second
- Sub-100ms response times for queries (verified with performance tests)
- Thousands of concurrent players

### Performance Guarantees

✅ **Rank queries and updates <100ms**: Verified with `scripts/performance_test.php`
- Average `getTopPlayers(10)`: ~20-80ms
- Average `getPlayerRank`: ~10-30ms
- Average `updatePlayerScore`: ~10-50ms

✅ **Leaderboard survives restarts**: 
- MySQL persistence for all player data
- Redis AOF (Append-Only File) enabled for durability
- Automatic recovery mechanism rebuilds Redis from MySQL on startup

✅ **Rankings always reflect latest scores**: 
- Redis atomic operations ensure consistency
- Real-time rank updates on every score change
- Verified with integration tests

✅ **Multiple concurrent updates**: 
- Redis sorted sets are thread-safe
- Atomic operations prevent race conditions
- Tested with 50+ concurrent players

## Project Structure

```
.
├── src/
│   ├── Config/          # Configuration management
│   ├── Factory/         # Service factory
│   ├── Model/           # Domain models
│   ├── Repository/      # Data access layer
│   └── Service/         # Business logic
├── tests/
│   ├── Integration/     # Integration tests
│   └── Unit/            # Unit tests
├── public/              # API entry point
├── scripts/             # Utility scripts
├── database/            # Database schema
└── docker-compose.yml   # Docker configuration
```

## Development

### Setup

Run the setup script to get started:

**Linux/Mac:**
```bash
chmod +x scripts/setup.sh
./scripts/setup.sh
```

**Windows:**
```powershell
.\scripts\setup.ps1
```

### Code Style

- PSR-4 autoloading
- PSR-12 coding standards
- Type hints for all methods
- Interface-based design

### Commits

This project follows meaningful commit messages:
- `feat: Add player score update endpoint`
- `test: Add unit tests for leaderboard service`
- `refactor: Improve Redis connection handling`
- `docs: Update API documentation`

See [CONTRIBUTING.md](CONTRIBUTING.md) for more details.

## License

MIT License

## Author

Senior Backend Developer Interview Task

