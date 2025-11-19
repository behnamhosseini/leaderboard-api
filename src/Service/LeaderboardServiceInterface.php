<?php

namespace GameLadder\Service;

use GameLadder\Model\Player;

interface LeaderboardServiceInterface
{
    /**
     * Add or update a player's score
     */
    public function updatePlayerScore(string $playerId, int $score): void;

    /**
     * Update multiple players' scores in a single operation
     * @param array<string, int> $updates Array of player_id => score
     */
    public function updatePlayerScoresBatch(array $updates): void;

    /**
     * Get top N players
     * @return Player[]
     */
    public function getTopPlayers(int $limit): array;

    /**
     * Get a player's current rank and score
     */
    public function getPlayerRank(string $playerId): ?Player;

    /**
     * Get total number of players
     */
    public function getTotalPlayers(): int;
}

