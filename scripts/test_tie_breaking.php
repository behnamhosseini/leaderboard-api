<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;

Config::load();

$service = ServiceFactory::createLeaderboardService();

echo "=== Tie-Breaking Test ===\n\n";

$service->getTotalPlayers();

$score = 1000;
$timestamp = time();

echo "Step 1: Adding two players with same score ({$score})...\n";
$service->updatePlayerScore('player1', $score);
usleep(100000);
$service->updatePlayerScore('player2', $score);

echo "Step 2: Checking ranks...\n";
$player1 = $service->getPlayerRank('player1');
$player2 = $service->getPlayerRank('player2');

echo "  player1: Score = {$player1->getScore()}, Rank = {$player1->getRank()}\n";
echo "  player2: Score = {$player2->getScore()}, Rank = {$player2->getRank()}\n";

if ($player1->getScore() === $player2->getScore() && $player1->getScore() === $score) {
    echo "\n PASS: Both players have the same score ({$score})\n";
} else {
    echo "\n FAIL: Scores don't match\n";
    exit(1);
}

if ($player1->getRank() !== $player2->getRank()) {
    echo "PASS: Tie-breaking works! Players have different ranks despite same score\n";
    echo "  (Player who updated first should have better rank)\n";
} else {
    echo "FAIL: Tie-breaking not working! Both players have same rank\n";
    exit(1);
}

echo "\nStep 3: Testing with three players with same score...\n";
$service->updatePlayerScore('player3', $score);
usleep(100000);
$service->updatePlayerScore('player4', $score);
usleep(100000);
$service->updatePlayerScore('player5', $score);

$topPlayers = $service->getTopPlayers(10);
$sameScorePlayers = array_filter($topPlayers, fn($p) => $p->getScore() === $score);

echo "  Found " . count($sameScorePlayers) . " players with score {$score}:\n";
foreach ($sameScorePlayers as $player) {
    echo "    {$player->getPlayerId()}: Rank {$player->getRank()}\n";
}

$ranks = array_map(fn($p) => $p->getRank(), $sameScorePlayers);
$uniqueRanks = array_unique($ranks);

if (count($ranks) === count($uniqueRanks)) {
    echo "\n PASS: All players with same score have different ranks (tie-breaking works)\n";
} else {
    echo "\n FAIL: Some players with same score have duplicate ranks\n";
    exit(1);
}

echo "\n=== Tie-Breaking Test Complete ===\n";

