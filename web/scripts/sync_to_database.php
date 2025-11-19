<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;

Config::load();

$recoveryService = ServiceFactory::createRecoveryService();

$synced = $recoveryService->syncToDatabase();
echo "Synced {$synced} players from Redis to MySQL\n";

