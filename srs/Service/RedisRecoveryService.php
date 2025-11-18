<?php

namespace GameLadder\Service;

use GameLadder\Repository\PlayerRepositoryInterface;
use Predis\ClientInterface;

class RedisRecoveryService
{
    private const LEADERBOARD_KEY = 'leaderboard:scores';

    public function __construct(private ClientInterface           $redis, private PlayerRepositoryInterface $playerRepository){}

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
}

