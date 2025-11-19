<?php

namespace GameLadder\Service;

use GameLadder\Model\Player;
use GameLadder\Repository\PlayerRepositoryInterface;
use Predis\ClientInterface;
use Predis\Connection\ConnectionException;
use Predis\Response\ServerException;
use RuntimeException;

class RedisLeaderboardService implements LeaderboardServiceInterface
{
    private const LEADERBOARD_KEY = 'leaderboard:scores';
    private static ?string $luaScript = null;

    public function __construct(private ClientInterface $redis, private PlayerRepositoryInterface $playerRepository) {}

    private function getLuaScript(): string
    {
        if (self::$luaScript === null) {
            $scriptPath = __DIR__ . '/../../scripts/update_score.lua';
            if (!file_exists($scriptPath)) {
                throw new RuntimeException("Lua script not found: {$scriptPath}");
            }
            $script = file_get_contents($scriptPath);
            if ($script === false) {
                throw new RuntimeException("Failed to read Lua script: {$scriptPath}");
            }
            self::$luaScript = $script;
        }
        return self::$luaScript;
    }

    public function updatePlayerScore(string $playerId, int $score): void
    {
        try {
            $player = new Player($playerId, $score);
            $this->playerRepository->save($player);
        } catch (\PDOException $e) {
            throw new RuntimeException("Database error while saving player: " . $e->getMessage(), 0, $e);
        }

        try {
            $this->redis->eval(
                $this->getLuaScript(),
                1,
                self::LEADERBOARD_KEY,
                $playerId,
                $score,
                time()
            );
        } catch (ConnectionException $e) {
            error_log("Redis connection error after MySQL save: " . $e->getMessage());
            throw new RuntimeException("Redis connection error: " . $e->getMessage(), 0, $e);
        } catch (ServerException $e) {
            error_log("Redis server error after MySQL save: " . $e->getMessage());
            throw new RuntimeException("Redis server error: " . $e->getMessage(), 0, $e);
        }
    }

    public function getTopPlayers(int $limit): array
    {
        try {
            $raw = $this->redis->zrevrange(self::LEADERBOARD_KEY, 0, $limit - 1, ['withscores' => true]);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Redis connection error: " . $e->getMessage(), 0, $e);
        } catch (ServerException $e) {
            throw new RuntimeException("Redis server error: " . $e->getMessage(), 0, $e);
        }

        $players = [];
        $rank = 1;

        foreach ($raw as $playerId => $compositeScore) {
            $score = (int) floor($compositeScore);
            $players[] = new Player($playerId, $score, $rank++);
        }
        return $players;
    }

    public function getPlayerRank(string $playerId): ?Player
    {
        try {
            $score = $this->redis->zscore(self::LEADERBOARD_KEY, $playerId);
            if ($score === null) {
                return null;
            }
            $rank = $this->redis->zrevrank(self::LEADERBOARD_KEY, $playerId);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Redis connection error: " . $e->getMessage(), 0, $e);
        } catch (ServerException $e) {
            throw new RuntimeException("Redis server error: " . $e->getMessage(), 0, $e);
        }
        
        if ($rank === null) {
            return null;
        }

        try {
            $player = $this->playerRepository->findById($playerId);
        } catch (\PDOException $e) {
            throw new RuntimeException("Database error while fetching player: " . $e->getMessage(), 0, $e);
        }

        if (!$player) {
            $player = new Player($playerId, (int)$score);
        }

        $player->setScore((int)$score);
        $player->setRank($rank + 1);

        return $player;
    }

    public function getTotalPlayers(): int
    {
        try {
            return $this->redis->zcard(self::LEADERBOARD_KEY);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Redis connection error: " . $e->getMessage(), 0, $e);
        } catch (ServerException $e) {
            throw new RuntimeException("Redis server error: " . $e->getMessage(), 0, $e);
        }
    }
}

