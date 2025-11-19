<?php

namespace GameLadder\Service;

use GameLadder\Model\Player;
use GameLadder\Repository\PlayerRepositoryInterface;
use Predis\ClientInterface;
use Predis\Connection\ConnectionException;
use Predis\Response\ServerException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RedisRecoveryService
{
    private const LEADERBOARD_KEY = 'leaderboard:scores';
    private const LOCK_KEY = 'leaderboard:recovery:lock';
    private const LOCK_TTL = 300; // 5 minutes

    public function __construct(
        private ClientInterface $redis,
        private PlayerRepositoryInterface $playerRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Acquire lock for recovery operation
     */
    private function acquireLock(): bool
    {
        try {
            $lockValue = uniqid('', true);
            // Predis set command with options: set(key, value, 'EX', seconds, 'NX')
            // Returns Status object with payload "OK" on success, null if NX condition fails (key exists)
            $acquired = $this->redis->set(self::LOCK_KEY, $lockValue, 'EX', self::LOCK_TTL, 'NX');
            return $acquired !== null;
        } catch (\Exception $e) {
            $this->logger->error("Failed to acquire recovery lock", ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Release lock for recovery operation
     */
    private function releaseLock(): void
    {
        try {
            $this->redis->del(self::LOCK_KEY);
        } catch (\Exception $e) {
            $this->logger->error("Failed to release recovery lock", ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Rebuild Redis leaderboard from MySQL database
     * This should be called on application startup to ensure Redis is synced
     */
    public function rebuildLeaderboardFromDatabase(): int
    {
        if (!$this->acquireLock()) {
            $this->logger->warning("Recovery already in progress, skipping rebuild");
            return 0;
        }

        try {
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
            
            $count = count($players);
            $this->logger->info("Rebuilt leaderboard from database", ['players_count' => $count]);
            
            return $count;
        } finally {
            $this->releaseLock();
        }
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
        if (!$this->acquireLock()) {
            $this->logger->warning("Sync already in progress, skipping");
            return 0;
        }

        try {
            try {
                $raw = $this->redis->zrange(self::LEADERBOARD_KEY, 0, -1, ['withscores' => true]);
            } catch (ConnectionException $e) {
                $this->logger->error("Redis connection error during sync", ['exception' => $e->getMessage()]);
                return 0;
            } catch (ServerException $e) {
                $this->logger->error("Redis server error during sync", ['exception' => $e->getMessage()]);
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
                    $this->logger->error("Failed to sync player to database", [
                        'player_id' => $playerId,
                        'exception' => $e->getMessage()
                    ]);
                }
            }

            if ($synced > 0) {
                $this->logger->info("Synced players to database", ['synced_count' => $synced]);
            }
            
            return $synced;
        } finally {
            $this->releaseLock();
        }
    }
}

