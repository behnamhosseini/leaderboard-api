<?php

namespace GameLadder\Tests\Unit\Service;

use GameLadder\Model\Player;
use GameLadder\Repository\PlayerRepositoryInterface;
use GameLadder\Service\RedisLeaderboardService;
use PHPUnit\Framework\TestCase;
use Predis\ClientInterface;

class RedisLeaderboardServiceTest extends TestCase
{
    private ClientInterface $redis;
    private PlayerRepositoryInterface $repository;
    private RedisLeaderboardService $service;

    protected function setUp(): void
    {
        // Create mock for ClientInterface - Predis uses magic methods via __call
        // We need to mock both abstract methods and magic methods
        $this->redis = $this->getMockBuilder(ClientInterface::class)
            ->onlyMethods(['getCommandFactory', 'getOptions', 'connect', 'disconnect', 'getConnection', 'executeCommand', 'createCommand', '__call'])
            ->addMethods(['eval', 'zrevrange', 'zscore', 'zrevrank', 'zcard', 'pipeline'])
            ->getMock();
        
        $this->repository = $this->createMock(PlayerRepositoryInterface::class);
        $this->service = new RedisLeaderboardService($this->redis, $this->repository);
    }

    public function testUpdatePlayerScore(): void
    {
        $playerId = 'player1';
        $score = 100;

        $this->redis->expects($this->once())
            ->method('eval')
            ->willReturn(1); // Return rank

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Player $player) use ($playerId, $score) {
                return $player->getPlayerId() === $playerId && $player->getScore() === $score;
            }));

        $this->service->updatePlayerScore($playerId, $score);
    }

    public function testGetTopPlayers(): void
    {
        $limit = 3;
        $playerData = [
            'player1' => 300,
            'player2' => 200,
            'player3' => 100,
        ];

        $this->redis->expects($this->once())
            ->method('zrevrange')
            ->willReturn($playerData);

        $result = $this->service->getTopPlayers($limit);

        $this->assertCount(3, $result);
        $this->assertEquals('player1', $result[0]->getPlayerId());
        $this->assertEquals(300, $result[0]->getScore());
        $this->assertEquals(1, $result[0]->getRank());
    }

    public function testGetPlayerRank(): void
    {
        $playerId = 'player1';
        $score = 150;
        $rank = 2;

        $this->redis->expects($this->once())
            ->method('zscore')
            ->willReturn($score);

        $this->redis->expects($this->once())
            ->method('zrevrank')
            ->willReturn($rank);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($playerId)
            ->willReturn(new Player($playerId, 0));

        $result = $this->service->getPlayerRank($playerId);

        $this->assertNotNull($result);
        $this->assertEquals($playerId, $result->getPlayerId());
        $this->assertEquals($score, $result->getScore());
        $this->assertEquals($rank + 1, $result->getRank()); // Convert to 1-indexed
    }

    public function testGetPlayerRankReturnsNullWhenPlayerNotFound(): void
    {
        $playerId = 'nonexistent';

        $this->redis->expects($this->once())
            ->method('zscore')
            ->willReturn(null);

        $result = $this->service->getPlayerRank($playerId);

        $this->assertNull($result);
    }

    public function testGetTotalPlayers(): void
    {
        $total = 50;

        $this->redis->expects($this->once())
            ->method('zcard')
            ->willReturn($total);

        $result = $this->service->getTotalPlayers();

        $this->assertEquals($total, $result);
    }

    public function testUpdatePlayerScoreContinuesWhenMySQLFails(): void
    {
        $playerId = 'player1';
        $score = 100;

        $this->redis->expects($this->once())
            ->method('eval')
            ->willReturn(1);

        $this->repository->expects($this->once())
            ->method('save')
            ->willThrowException(new \PDOException('Connection failed'));

        $this->service->updatePlayerScore($playerId, $score);

        $this->assertTrue(true);
    }
}

