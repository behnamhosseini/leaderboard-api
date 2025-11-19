<?php

namespace GameLadder\Repository;

use GameLadder\Model\Player;

interface PlayerRepositoryInterface
{
    /**
     * Save or update player data
     */
    public function save(Player $player): void;

    /**
     * Find player by ID
     */
    public function findById(string $playerId): ?Player;

    /**
     * Get all players ordered by score descending
     * @return Player[]
     */
    public function findAllOrderedByScore(int $limit = null): array;
}

