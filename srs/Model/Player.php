<?php

namespace GameLadder\Model;

class Player
{
    public function __construct(
        private string $playerId,
        private int $score,
        private ?int $rank = null
    ) {
    }

    public function getPlayerId(): string
    {
        return $this->playerId;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(?int $rank): void
    {
        $this->rank = $rank;
    }

    public function toArray(): array
    {
        return [
            'player_id' => $this->playerId,
            'score' => $this->score,
            'rank' => $this->rank,
        ];
    }
}

