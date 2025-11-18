<?php

namespace GameLadder\Repository;

use GameLadder\Model\Player;
use PDO;

class DatabasePlayerRepository implements PlayerRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function save(Player $player): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO players (player_id, score, updated_at) 
             VALUES (:player_id, :score, NOW())
             ON DUPLICATE KEY UPDATE score = :score, updated_at = NOW()'
        );

        $stmt->execute([
            'player_id' => $player->getPlayerId(),
            'score' => $player->getScore(),
        ]);
    }

    public function findById(string $playerId): ?Player
    {
        $stmt = $this->pdo->prepare('SELECT player_id, score FROM players WHERE player_id = :player_id');
        $stmt->execute(['player_id' => $playerId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new Player($data['player_id'], (int)$data['score']);
    }

    public function findAllOrderedByScore(int $limit = null): array
    {
        $sql = 'SELECT player_id, score FROM players ORDER BY score DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $stmt = $this->pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $players = [];
        foreach ($results as $data) {
            $players[] = new Player($data['player_id'], (int)$data['score']);
        }

        return $players;
    }
}

