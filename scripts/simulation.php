<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;

Config::load();

$service = ServiceFactory::createLeaderboardService();

echo "=== Real-Time Player Ladder Simulation ===\n\n";

// Initialize some players
$playerIds = ['alice', 'bob', 'charlie', 'diana', 'eve', 'frank', 'grace', 'henry'];
$initialScores = [
    'alice' => 1000,
    'bob' => 950,
    'charlie' => 900,
    'diana' => 850,
    'eve' => 800,
    'frank' => 750,
    'grace' => 700,
    'henry' => 650,
];

echo "Step 1: Initializing players with scores...\n";
foreach ($initialScores as $playerId => $score) {
    $service->updatePlayerScore($playerId, $score);
    echo "  - {$playerId}: {$score} points\n";
}

echo "\nStep 2: Current Leaderboard (Top 5):\n";
$topPlayers = $service->getTopPlayers(5);
foreach ($topPlayers as $player) {
    echo "  Rank {$player->getRank()}: {$player->getPlayerId()} - {$player->getScore()} points\n";
}

echo "\nStep 3: Simulating score updates...\n";
for ($round = 1; $round <= 5; $round++) {
    echo "\n--- Round {$round} ---\n";
    
    // Randomly update 3 players
    $selectedPlayers = array_rand($playerIds, 3);
    foreach ($selectedPlayers as $index) {
        $playerId = $playerIds[$index];
        $currentPlayer = $service->getPlayerRank($playerId);
        $newScore = $currentPlayer->getScore() + rand(50, 200);
        
        $service->updatePlayerScore($playerId, $newScore);
        $updatedPlayer = $service->getPlayerRank($playerId);
        
        echo "  {$playerId}: {$currentPlayer->getScore()} → {$newScore} (Rank: {$currentPlayer->getRank()} → {$updatedPlayer->getRank()})\n";
    }
    
    echo "\n  Updated Top 3:\n";
    $topPlayers = $service->getTopPlayers(3);
    foreach ($topPlayers as $player) {
        echo "    Rank {$player->getRank()}: {$player->getPlayerId()} - {$player->getScore()} points\n";
    }
    
    usleep(500000); // 0.5 second delay
}

echo "\n\nStep 4: Final Leaderboard:\n";
$topPlayers = $service->getTopPlayers(8);
foreach ($topPlayers as $player) {
    echo "  Rank {$player->getRank()}: {$player->getPlayerId()} - {$player->getScore()} points\n";
}

echo "\nStep 5: Individual Player Ranks:\n";
foreach ($playerIds as $playerId) {
    $player = $service->getPlayerRank($playerId);
    if ($player) {
        echo "  {$playerId}: Rank {$player->getRank()}, Score {$player->getScore()}\n";
    }
}

echo "\nTotal Players: " . $service->getTotalPlayers() . "\n";
echo "\n=== Simulation Complete ===\n";

