<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;

Config::load();

$service = ServiceFactory::createLeaderboardService();

echo "=== Performance Test: 100+ Updates Per Second ===\n\n";

$numPlayers = 100;
$updatesPerSecond = 150;
$testDuration = 5; // seconds

// Initialize players
echo "Initializing {$numPlayers} players...\n";
$playerIds = [];
for ($i = 1; $i <= $numPlayers; $i++) {
    $playerId = "player_{$i}";
    $playerIds[] = $playerId;
    $service->updatePlayerScore($playerId, rand(100, 1000));
}
echo "Done.\n\n";

echo "Running performance test: {$updatesPerSecond} updates/second for {$testDuration} seconds...\n";
$startTime = microtime(true);
$updateCount = 0;
$endTime = $startTime + $testDuration;
$updateInterval = 1.0 / $updatesPerSecond;

while (microtime(true) < $endTime) {
    $loopStart = microtime(true);
    
    // Randomly select a player and update their score
    $randomPlayer = $playerIds[array_rand($playerIds)];
    $currentPlayer = $service->getPlayerRank($randomPlayer);
    $newScore = ($currentPlayer ? $currentPlayer->getScore() : 0) + rand(1, 50);
    
    $updateStart = microtime(true);
    $service->updatePlayerScore($randomPlayer, $newScore);
    $updateEnd = microtime(true);
    
    $updateCount++;
    
    // Calculate sleep time to maintain target updates/second
    $elapsed = microtime(true) - $loopStart;
    $sleepTime = max(0, $updateInterval - $elapsed);
    if ($sleepTime > 0) {
        usleep((int)($sleepTime * 1000000));
    }
}

$totalTime = microtime(true) - $startTime;
$actualUpdatesPerSecond = $updateCount / $totalTime;

echo "Test completed!\n\n";
echo "Results:\n";
echo "  Total updates: {$updateCount}\n";
echo "  Total time: " . number_format($totalTime, 2) . " seconds\n";
echo "  Actual updates/second: " . number_format($actualUpdatesPerSecond, 2) . "\n";
echo "  Target updates/second: {$updatesPerSecond}\n";

if ($actualUpdatesPerSecond >= 100) {
    echo "\n PASS: System handles 100+ updates/second\n";
} else {
    echo "\n FAIL: System did not meet 100 updates/second requirement\n";
}

// Test query performance
echo "\n=== Query Performance Test ===\n";
$queryCount = 1000;

// Test getTopPlayers
$startTime = microtime(true);
for ($i = 0; $i < $queryCount; $i++) {
    $service->getTopPlayers(10);
}
$topPlayersTime = (microtime(true) - $startTime) / $queryCount * 1000;

// Test getPlayerRank
$startTime = microtime(true);
for ($i = 0; $i < $queryCount; $i++) {
    $randomPlayer = $playerIds[array_rand($playerIds)];
    $service->getPlayerRank($randomPlayer);
}
$getRankTime = (microtime(true) - $startTime) / $queryCount * 1000;

echo "Average response times (over {$queryCount} queries):\n";
echo "  getTopPlayers(10): " . number_format($topPlayersTime, 2) . " ms\n";
echo "  getPlayerRank: " . number_format($getRankTime, 2) . " ms\n";

if ($topPlayersTime < 100 && $getRankTime < 100) {
    echo "\n PASS: All queries respond in under 100ms\n";
} else {
    echo "\n FAIL: Some queries exceed 100ms threshold\n";
}

echo "\n=== Performance Test Complete ===\n";

