<?php

namespace GameLadder\Service;

use GameLadder\Model\Player;
use GameLadder\Repository\PlayerRepositoryInterface;
use Predis\ClientInterface;

class RedisLeaderboardService implements LeaderboardServiceInterface
{
    private const LEADERBOARD_KEY = 'leaderboard:scores';

    public function __construct(private ClientInterface $redis, private PlayerRepositoryInterface $playerRepository) {}

    public function updatePlayerScore(string $playerId, int $score): void
    {
        $this->redis->zadd(self::LEADERBOARD_KEY, $score, $playerId);
        $player = new Player($playerId, $score);
        $this->playerRepository->save($player);
    }

    public function getTopPlayers(int $limit): array
    {
        $topPlayerIds = $this->redis->zrevrange(self::LEADERBOARD_KEY, 0, $limit - 1, true);

        $players = [];
        $rank = 1;

        foreach ($topPlayerIds as $playerId => $score) {
            $player = $this->playerRepository->findById($playerId);
            if (!$player) {
                $player = new Player($playerId, (int)$score);
            }
            $player->setScore((int)$score);
            $player->setRank($rank);
            $players[] = $player;
            $rank++;
        }

        return $players;
    }

    public function getPlayerRank(string $playerId): ?Player
    {
        $score = $this->redis->zscore(self::LEADERBOARD_KEY, $playerId);
        if ($score === null) {
            return null;
        }
        $rank = $this->redis->zrevrank(self::LEADERBOARD_KEY, $playerId);
        
        if ($rank === null) {
            return null;
        }
        $player = $this->playerRepository->findById($playerId);
        if (!$player) {
            $player = new Player($playerId, (int)$score);
        }

        $player->setScore((int)$score);
        $player->setRank($rank + 1);

        return $player;
    }

    public function getTotalPlayers(): int
    {
        return $this->redis->zcard(self::LEADERBOARD_KEY);
    }
}

