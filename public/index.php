<?php

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

Config::load();

// One-time Redis recovery
static $recoveryInitialized = false;
if (!$recoveryInitialized) {
    $recoveryInitialized = true;
    $recovery = ServiceFactory::createRecoveryService();

    if ($recovery->needsRecovery()) {
        $count = $recovery->rebuildLeaderboardFromDatabase();
        error_log("Redis recovery: Rebuilt leaderboard with {$count} players from MySQL");
    }
}

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

$app->add(function ($req, $handler) {
    $res = $handler->handle($req);
    return $res
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

$service = ServiceFactory::createLeaderboardService();

$badRequest = function (Response $r, $msg): Response {
    $r->getBody()->write(json_encode(['error' => $msg]));
    return $r->withStatus(400)->withHeader('Content-Type', 'application/json');
};

$ok = function (Response $r, $data): Response {
    $r->getBody()->write(json_encode($data));
    return $r->withHeader('Content-Type', 'application/json');
};

$validatePlayerId = function (string $id): bool {
    return $id !== '' && strlen($id) <= 255;
};


$app->post('/api/players/{playerId}/score', function (Request $req, Response $res, $args) use ($service, $validatePlayerId, $badRequest, $ok) {
    $playerId = trim($args['playerId'] ?? '');

    if (!$validatePlayerId($playerId)) {
        return $badRequest($res, 'Invalid player ID');
    }

    $data = json_decode((string)$req->getBody(), true);
    if (!is_array($data) || !isset($data['score']) || !is_numeric($data['score'])) {
        return $badRequest($res, 'Score must be a number');
    }

    $score = (int)$data['score'];
    if ($score < 0) {
        return $badRequest($res, 'Score must be >= 0');
    }

    $service->updatePlayerScore($playerId, $score);

    return $ok($res, [
        'message' => 'Score updated successfully',
        'player_id' => $playerId,
        'score' => $score,
    ]);
});

$app->get('/api/leaderboard/top', function (Request $req, Response $res) use ($service, $ok) {
    $limit = max(1, min((int)($req->getQueryParams()['limit'] ?? 10), 1000));
    $players = array_map(fn($p) => $p->toArray(), $service->getTopPlayers($limit));

    return $ok($res, [
        'players' => $players,
        'total' => count($players),
    ]);
});

$app->get('/api/players/{playerId}/rank', function (Request $req, Response $res, $args) use ($service, $validatePlayerId, $badRequest, $ok) {
    $playerId = trim($args['playerId'] ?? '');

    if (!$validatePlayerId($playerId)) {
        return $badRequest($res, 'Invalid player ID');
    }

    $player = $service->getPlayerRank($playerId);
    if (!$player) {
        $res->getBody()->write(json_encode(['error' => 'Player not found']));
        return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    return $ok($res, $player->toArray());
});

$app->get('/api/leaderboard/count', function (Request $req, Response $res) use ($service, $ok) {
    return $ok($res, ['total_players' => $service->getTotalPlayers()]);
});

$app->run();
