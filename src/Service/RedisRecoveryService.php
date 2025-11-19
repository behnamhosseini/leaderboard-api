<?php

namespace GameLadder\Service;

use GameLadder\Model\Player;
use GameLadder\Repository\PlayerRepositoryInterface;
use Predis\ClientInterface;
use Predis\Connection\ConnectionException;
use Predis\Response\ServerException;
use RuntimeException;

class RedisRecoveryService
{
    private const LEADERBOARD_KEY = 'leaderboard:scores';

    public function __construct(private ClientInterface $redis, private PlayerRepositoryInterface $playerRepository){}

    /**
     * Rebuild Redis leaderboard from MySQL database
     * This should be called on application startup to ensure Redis is synced
     */
    public function rebuildLeaderboardFromDatabase(): int
    {
        $this->redis->del(self::LEADERBOARD_KEY);
        $players = $this->playerRepository->findAllOrderedByScore();

        if (empty($players)) {
            return 0;
        }

        $pipeline = $this->redis->pipeline();
        foreach ($players as $player) {
            $pipeline->zadd(self::LEADERBOARD_KEY, $player->getScore(), $player->getPlayerId());
        }
        $pipeline->execute();
        return count($players);
    }

    /**
     * Check if Redis leaderboard is empty and needs recovery
     */
    public function needsRecovery(): bool
    {
        $redisCount = $this->redis->zcard(self::LEADERBOARD_KEY);

        if ($redisCount === 0) {
            $players = $this->playerRepository->findAllOrderedByScore(1);
            return !empty($players);
        }

        return false;
    }

    /**
     * Sync all players from Redis to MySQL
     * This should be called periodically (e.g., every 30 seconds) to persist Redis data
     */
    public function syncToDatabase(): int
    {
        try {
            $raw = $this->redis->zrange(self::LEADERBOARD_KEY, 0, -1, ['withscores' => true]);
        } catch (ConnectionException $e) {
            error_log("Redis connection error during sync: " . $e->getMessage());
            return 0;
        } catch (ServerException $e) {
            error_log("Redis server error during sync: " . $e->getMessage());
            return 0;
        }

        if (empty($raw)) {
            return 0;
        }

        $synced = 0;
        foreach ($raw as $playerId => $compositeScore) {
            try {
                $score = (int) floor($compositeScore);
                $player = new Player($playerId, $score);
                $this->playerRepository->save($player);
                $synced++;
            } catch (\PDOException $e) {
                error_log("Failed to sync player {$playerId} to database: " . $e->getMessage());
            }
        }

        return $synced;
    }
}

