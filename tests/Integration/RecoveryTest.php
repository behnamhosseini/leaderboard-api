<?php

namespace GameLadder\Tests\Integration;

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;
use GameLadder\Service\LeaderboardServiceInterface;
use GameLadder\Service\RedisRecoveryService;
use PHPUnit\Framework\TestCase;
use PDO;
use Predis\Client;

class RecoveryTest extends TestCase
{
    private LeaderboardServiceInterface $service;
    private RedisRecoveryService $recoveryService;
    private Client $redis;
    private PDO $pdo;

    protected function setUp(): void
    {
        Config::load();
        $this->service = ServiceFactory::createLeaderboardService();
        $this->recoveryService = ServiceFactory::createRecoveryService();
        $this->redis = new Client([
            'host' => Config::get('REDIS_HOST', '127.0.0.1'),
            'port' => Config::getInt('REDIS_PORT', 6379),
        ]);

        $this->redis->flushdb();
        
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Config::get('DB_HOST', '127.0.0.1'),
            Config::get('DB_PORT', '3306'),
            Config::get('DB_NAME', 'leaderboard_db')
        );
        $this->pdo = new PDO(
            $dsn,
            Config::get('DB_USER', 'root'),
            Config::get('DB_PASS', 'root'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->pdo->exec('TRUNCATE TABLE players');
    }

    protected function tearDown(): void
    {
        $this->redis->flushdb();
        $this->pdo->exec('TRUNCATE TABLE players');
    }

    public function testRecoveryAfterRedisRestart(): void
    {
        $this->service->updatePlayerScore('player1', 1000);
        $this->service->updatePlayerScore('player2', 2000);
        $this->service->updatePlayerScore('player3', 1500);

        $this->assertEquals(3, $this->service->getTotalPlayers());

        // Wait for write-through to complete (non-blocking writes may take time)
        usleep(500000); // 0.5 seconds to ensure write-through completes

        // Force sync to MySQL before flushing Redis
        // Use reflection to release any existing lock first
        $reflection = new \ReflectionClass($this->recoveryService);
        $releaseLockMethod = $reflection->getMethod('releaseLock');
        $releaseLockMethod->setAccessible(true);
        $releaseLockMethod->invoke($this->recoveryService);
        
        // Sync to ensure all data is in MySQL
        $synced = $this->recoveryService->syncToDatabase();
        
        // Verify that data exists in MySQL before flushing Redis
        // Check both via syncToDatabase return value and direct query
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM players');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $mysqlCount = (int)$result['count'];
        
        // If sync returned 0 but MySQL has data, that's fine (already synced via write-through)
        // If both are 0, then write-through failed
        if ($synced == 0 && $mysqlCount == 0) {
            $this->fail('No players in MySQL - write-through or sync failed');
        }

        $this->redis->flushdb();

        $this->assertEquals(0, $this->service->getTotalPlayers());

        // Release any existing lock before recovery (delete lock key directly)
        // Note: flushdb already deleted the lock, but we ensure it's gone
        // Also check if lock exists before deleting
        if ($this->redis->exists('leaderboard:recovery:lock')) {
            $this->redis->del('leaderboard:recovery:lock');
        }
        
        // Verify MySQL still has data after flushdb
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM players');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $mysqlCountAfterFlush = (int)$result['count'];
        $this->assertGreaterThan(0, $mysqlCountAfterFlush, 'MySQL should still have players after Redis flush');
        
        // Verify repository can fetch players
        $repository = ServiceFactory::createPlayerRepository();
        $playersFromRepo = $repository->findAllOrderedByScore();
        $this->assertCount(3, $playersFromRepo, 'Repository should return 3 players');
        
        $recoveredCount = $this->recoveryService->rebuildLeaderboardFromDatabase();
        $this->assertEquals(3, $recoveredCount, "Expected 3 players to be recovered from MySQL. MySQL had: {$mysqlCountAfterFlush}, Repository returned: " . count($playersFromRepo));

        $this->assertEquals(3, $this->service->getTotalPlayers());

        $topPlayers = $this->service->getTopPlayers(3);
        $this->assertCount(3, $topPlayers);
        $this->assertEquals('player2', $topPlayers[0]->getPlayerId());
        $this->assertEquals(2000, $topPlayers[0]->getScore());
        $this->assertEquals(1, $topPlayers[0]->getRank());

        $this->assertEquals('player3', $topPlayers[1]->getPlayerId());
        $this->assertEquals(1500, $topPlayers[1]->getScore());
        $this->assertEquals(2, $topPlayers[1]->getRank());

        $this->assertEquals('player1', $topPlayers[2]->getPlayerId());
        $this->assertEquals(1000, $topPlayers[2]->getScore());
        $this->assertEquals(3, $topPlayers[2]->getRank());
    }

    public function testNeedsRecovery(): void
    {
        $this->assertFalse($this->recoveryService->needsRecovery());

        $this->service->updatePlayerScore('player1', 1000);
        $this->service->updatePlayerScore('player2', 2000);

        // Wait for write-through to complete (non-blocking writes may take time)
        usleep(500000); // 0.5 seconds

        // Force sync to MySQL
        $reflection = new \ReflectionClass($this->recoveryService);
        $releaseLockMethod = $reflection->getMethod('releaseLock');
        $releaseLockMethod->setAccessible(true);
        $releaseLockMethod->invoke($this->recoveryService);
        
        $this->recoveryService->syncToDatabase();
        
        // Verify that data exists in MySQL
        $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM players');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertGreaterThan(0, (int)$result['count'], 'Players should be in MySQL');

        $this->redis->flushdb();

        // Verify that recovery is needed (Redis empty but MySQL has data)
        $this->assertTrue($this->recoveryService->needsRecovery());

        $this->recoveryService->rebuildLeaderboardFromDatabase();

        $this->assertFalse($this->recoveryService->needsRecovery());
    }

    public function testRecoveryWithEmptyDatabase(): void
    {
        $recoveredCount = $this->recoveryService->rebuildLeaderboardFromDatabase();
        $this->assertEquals(0, $recoveredCount);
        $this->assertFalse($this->recoveryService->needsRecovery());
    }
}

