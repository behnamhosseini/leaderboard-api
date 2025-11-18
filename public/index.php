<?php

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;
use GameLadder\Service\RedisRecoveryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

Config::load();

// Recover Redis from MySQL on startup if needed
$recoveryService = ServiceFactory::createRecoveryService();
if ($recoveryService->needsRecovery()) {
    $recoveredCount = $recoveryService->rebuildLeaderboardFromDatabase();
    error_log("Redis recovery: Rebuilt leaderboard with {$recoveredCount} players from MySQL");
}

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

// CORS middleware
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

$leaderboardService = ServiceFactory::createLeaderboardService();

// Update player score
$app->post('/api/players/{playerId}/score', function (Request $request, Response $response, array $args) use ($leaderboardService) {
    $playerId = $args['playerId'];
    $data = json_decode($request->getBody()->getContents(), true);

    if (!isset($data['score']) || !is_numeric($data['score'])) {
        $response->getBody()->write(json_encode(['error' => 'Invalid score']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $score = (int)$data['score'];
    $leaderboardService->updatePlayerScore($playerId, $score);

    $response->getBody()->write(json_encode([
        'message' => 'Score updated successfully',
        'player_id' => $playerId,
        'score' => $score,
    ]));

    return $response->withHeader('Content-Type', 'application/json');
});

// Get top players
$app->get('/api/leaderboard/top', function (Request $request, Response $response) use ($leaderboardService) {
    $queryParams = $request->getQueryParams();
    $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;

    if ($limit < 1 || $limit > 1000) {
        $limit = 10;
    }

    $players = $leaderboardService->getTopPlayers($limit);
    $result = array_map(fn($player) => $player->toArray(), $players);

    $response->getBody()->write(json_encode([
        'players' => $result,
        'total' => count($result),
    ]));

    return $response->withHeader('Content-Type', 'application/json');
});

// Get player rank
$app->get('/api/players/{playerId}/rank', function (Request $request, Response $response, array $args) use ($leaderboardService) {
    $playerId = $args['playerId'];
    $player = $leaderboardService->getPlayerRank($playerId);

    if ($player === null) {
        $response->getBody()->write(json_encode(['error' => 'Player not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode($player->toArray()));
    return $response->withHeader('Content-Type', 'application/json');
});

// Get total players count
$app->get('/api/leaderboard/count', function (Request $request, Response $response) use ($leaderboardService) {
    $total = $leaderboardService->getTotalPlayers();

    $response->getBody()->write(json_encode([
        'total_players' => $total,
    ]));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();

