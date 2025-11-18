<?php

namespace Tests\Unit\Repository;

use GameLadder\Model\Player;
use GameLadder\Repository\DatabasePlayerRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class DatabasePlayerRepositoryTest extends TestCase
{
    private PDO $pdo;
    private DatabasePlayerRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->repository = new DatabasePlayerRepository($this->pdo);
    }

    public function testSaveNewPlayer(): void
    {
        $player = new Player('player1', 100);
        $stmt = $this->createMock(PDOStatement::class);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO players'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                'player_id' => 'player1',
                'score' => 100,
            ]);

        $this->repository->save($player);
    }

    public function testFindById(): void
    {
        $playerId = 'player1';
        $stmt = $this->createMock(PDOStatement::class);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['player_id' => $playerId]);

        $stmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([
                'player_id' => 'player1',
                'score' => 100,
            ]);

        $result = $this->repository->findById($playerId);

        $this->assertInstanceOf(Player::class, $result);
        $this->assertEquals('player1', $result->getPlayerId());
        $this->assertEquals(100, $result->getScore());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $playerId = 'nonexistent';
        $stmt = $this->createMock(PDOStatement::class);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['player_id' => $playerId]);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $result = $this->repository->findById($playerId);

        $this->assertNull($result);
    }

    public function testFindAllOrderedByScore(): void
    {
        $stmt = $this->createMock(PDOStatement::class);

        $this->pdo->expects($this->once())
            ->method('query')
            ->with($this->stringContains('ORDER BY score DESC'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([
                ['player_id' => 'player1', 'score' => 300],
                ['player_id' => 'player2', 'score' => 200],
            ]);

        $result = $this->repository->findAllOrderedByScore();

        $this->assertCount(2, $result);
        $this->assertEquals('player1', $result[0]->getPlayerId());
        $this->assertEquals(300, $result[0]->getScore());
    }
}

