<?php

namespace GameLadder\Tests\Unit\Model;

use GameLadder\Model\Player;
use PHPUnit\Framework\TestCase;

class PlayerTest extends TestCase
{
    public function testPlayerCreation(): void
    {
        $player = new Player('player1', 100);

        $this->assertEquals('player1', $player->getPlayerId());
        $this->assertEquals(100, $player->getScore());
        $this->assertNull($player->getRank());
    }

    public function testPlayerWithRank(): void
    {
        $player = new Player('player1', 100, 5);

        $this->assertEquals(5, $player->getRank());
    }

    public function testSetScore(): void
    {
        $player = new Player('player1', 100);
        $player->setScore(200);

        $this->assertEquals(200, $player->getScore());
    }

    public function testSetRank(): void
    {
        $player = new Player('player1', 100);
        $player->setRank(10);

        $this->assertEquals(10, $player->getRank());
    }

    public function testToArray(): void
    {
        $player = new Player('player1', 100, 5);
        $array = $player->toArray();

        $this->assertEquals([
            'player_id' => 'player1',
            'score' => 100,
            'rank' => 5,
        ], $array);
    }
}

