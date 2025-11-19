# API Documentation

## Base URL

```
http://localhost:8000
```

## Endpoints

### 1. Update Player Score

Updates or adds a player's score to the leaderboard.

**Endpoint:** `POST /api/players/{playerId}/score`

**Path Parameters:**
- `playerId` (string, required): Unique identifier for the player

**Request Body:**
```json
{
  "score": 1500
}
```

**Response (200 OK):**
```json
{
  "message": "Score updated successfully",
  "player_id": "player123",
  "score": 1500
}
```

**Error Response (400 Bad Request):**
```json
{
  "error": "Invalid score"
}
```

**Example:**
```bash
curl -X POST http://localhost:8000/api/players/alice/score \
  -H "Content-Type: application/json" \
  -d '{"score": 1500}'
```

---

### 2. Batch Update Player Scores

Updates multiple players' scores in a single request.

**Endpoint:** `POST /api/players/batch`

**Request Body:**
```json
{
  "updates": [
    {"player_id": "player1", "score": 1000},
    {"player_id": "player2", "score": 2000},
    {"player_id": "player3", "score": 1500}
  ]
}
```

**Response (200 OK):**
```json
{
  "message": "Batch update completed successfully",
  "updated_count": 3,
  "updates": [
    {"player_id": "player1", "score": 1000},
    {"player_id": "player2", "score": 2000},
    {"player_id": "player3", "score": 1500}
  ]
}
```

**Error Response (400 Bad Request):**
```json
{
  "error": "Validation errors: Update at index 0: invalid player_id; Update at index 1: score must be >= 0"
}
```

**Limitations:**
- Maximum batch size: 1000 updates per request
- All updates are processed atomically in Redis
- MySQL updates are non-blocking (best-effort)

**Example:**
```bash
curl -X POST http://localhost:8000/api/players/batch \
  -H "Content-Type: application/json" \
  -d '{
    "updates": [
      {"player_id": "alice", "score": 1500},
      {"player_id": "bob", "score": 2000},
      {"player_id": "charlie", "score": 1200}
    ]
  }'
```

**Performance:**
- Uses Redis pipeline for efficient batch operations
- Response time: ~10-50ms per update (e.g., 10 updates = ~100-500ms total)
- Much more efficient than multiple individual requests

---

### 3. Get Top Players

Retrieves the top N players from the leaderboard.

**Endpoint:** `GET /api/leaderboard/top`

**Query Parameters:**
- `limit` (integer, optional): Number of top players to retrieve (default: 10, max: 1000)

**Response (200 OK):**
```json
{
  "players": [
    {
      "player_id": "alice",
      "score": 3000,
      "rank": 1
    },
    {
      "player_id": "bob",
      "score": 2500,
      "rank": 2
    },
    {
      "player_id": "charlie",
      "score": 2000,
      "rank": 3
    }
  ],
  "total": 3
}
```

**Example:**
```bash
curl http://localhost:8000/api/leaderboard/top?limit=10
```

---

### 4. Get Player Rank

Retrieves a specific player's current rank and score.

**Endpoint:** `GET /api/players/{playerId}/rank`

**Path Parameters:**
- `playerId` (string, required): Unique identifier for the player

**Response (200 OK):**
```json
{
  "player_id": "alice",
  "score": 1500,
  "rank": 5
}
```

**Error Response (404 Not Found):**
```json
{
  "error": "Player not found"
}
```

**Example:**
```bash
curl http://localhost:8000/api/players/alice/rank
```

---

### 5. Get Total Players Count

Returns the total number of players in the leaderboard.

**Endpoint:** `GET /api/leaderboard/count`

**Response (200 OK):**
```json
{
  "total_players": 150
}
```

**Example:**
```bash
curl http://localhost:8000/api/leaderboard/count
```

---

## Response Times

All endpoints are designed to respond in under 100ms:
- Update Score: ~10-50ms
- Get Top Players: ~20-80ms (depending on limit)
- Get Player Rank: ~10-30ms
- Get Total Count: ~5-15ms

## Error Handling

All endpoints return appropriate HTTP status codes:
- `200 OK`: Successful request
- `400 Bad Request`: Invalid request parameters
- `404 Not Found`: Resource not found
- `500 Internal Server Error`: Server error

Error responses follow this format:
```json
{
  "error": "Error message description"
}
```

