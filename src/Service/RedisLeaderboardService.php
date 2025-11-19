<?php

namespace GameLadder\Service;

use GameLadder\Model\Player;
use GameLadder\Repository\PlayerRepositoryInterface;
use Predis\ClientInterface;

class RedisLeaderboardService implements LeaderboardServiceInterface
{
    private const LEADERBOARD_KEY = 'leaderboard:scores';
    private static ?string $luaScript = null;

    public function __construct(private ClientInterface $redis, private PlayerRepositoryInterface $playerRepository) {}

    private function getLuaScript(): string
    {
        if (self::$luaScript === null) {
            self::$luaScript = file_get_contents(__DIR__ . '/../../scripts/update_score.lua');
        }
        return self::$luaScript;
    }

    public function updatePlayerScore(string $playerId, int $score): void
    {
        $this->redis->eval(
            $this->getLuaScript(),
            1,
            self::LEADERBOARD_KEY,
            $playerId,
            $score,
            time()
        );
        $player = new Player($playerId, $score);
        $this->playerRepository->save($player);
    }

    public function getTopPlayers(int $limit): array
    {
        $raw = $this->redis->zrevrange(self::LEADERBOARD_KEY, 0, $limit - 1, ['withscores' => true]);

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

