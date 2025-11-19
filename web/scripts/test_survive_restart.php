<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;
use Predis\Client;

Config::load();

$service = ServiceFactory::createLeaderboardService();
$recoveryService = ServiceFactory::createRecoveryService();
$redis = new Client([
    'host' => Config::get('REDIS_HOST', '127.0.0.1'),
    'port' => Config::getInt('REDIS_PORT', 6379),
]);

echo "=== Survive Restart Test ===\n\n";

echo "Step 1: Adding players...\n";
$players = [
    'alice' => 1500,
    'bob' => 2000,
    'charlie' => 1200,
    'diana' => 1800,
    'eve' => 1000,
];

foreach ($players as $playerId => $score) {
    $service->updatePlayerScore($playerId, $score);
    echo "  {$playerId}: {$score}\n";
}

echo "\nStep 2: Waiting for MySQL sync (if periodic sync is enabled)...\n";
sleep(2);

echo "\nStep 3: Simulating Redis restart (flushing Redis)...\n";
$redis->flushdb();
echo "  Redis flushed - simulating restart\n";

$redisCount = $service->getTotalPlayers();
echo "\nStep 4: Verifying Redis is empty...\n";
echo "  Redis players count: {$redisCount}\n";

if ($redisCount === 0) {
    echo "   Redis is empty (as expected after restart)\n";
} else {
    echo "   Redis should be empty but has {$redisCount} players\n";
    exit(1);
}

echo "\nStep 5: Checking if recovery is needed...\n";
$needsRecovery = $recoveryService->needsRecovery();
echo "  Needs recovery: " . ($needsRecovery ? 'YES' : 'NO') . "\n";

if (!$needsRecovery) {
    echo "  WARNING: Recovery not needed - MySQL might not have data yet\n";
    echo "  (This is expected if periodic sync hasn't run yet)\n";
    echo "  Running sync manually to ensure MySQL has data...\n";
    $recoveryService->syncToDatabase();
    sleep(1);
    $needsRecovery = $recoveryService->needsRecovery();
    echo "  Needs recovery after sync: " . ($needsRecovery ? 'YES' : 'NO') . "\n";
}

if ($needsRecovery) {
    echo "\nStep 6: Recovering from MySQL...\n";
    $recoveredCount = $recoveryService->rebuildLeaderboardFromDatabase();
    echo "  Recovered {$recoveredCount} players\n";

    echo "\nStep 7: Verifying recovery...\n";
    $recoveredCount = $service->getTotalPlayers();
    echo "  Total players after recovery: {$recoveredCount}\n";
    
    if ($recoveredCount >= count($players)) {
        echo "  Recovery successful!\n";
    } else {
        echo "  Recovery failed - expected at least " . count($players) . " players\n";
        exit(1);
    }

    echo "\nStep 8: Verifying rankings after recovery...\n";
    $topPlayers = $service->getTopPlayers(5);
    foreach ($topPlayers as $player) {
        echo "  Rank {$player->getRank()}: {$player->getPlayerId()} - {$player->getScore()}\n";
    }

    $alice = $service->getPlayerRank('alice');
    if ($alice && $alice->getScore() === 1500) {
        echo "\n  Player 'alice' recovered correctly: Score = {$alice->getScore()}, Rank = {$alice->getRank()}\n";
    } else {
        echo "\n  Player 'alice' not recovered correctly\n";
        exit(1);
    }
} else {
    echo "\n Skipping recovery test - MySQL doesn't have data yet\n";
    echo "  This is expected if periodic sync hasn't run\n";
    echo "  To test recovery, run: php scripts/sync_to_database.php first\n";
}

echo "\n=== Survive Restart Test Complete ===\n";

