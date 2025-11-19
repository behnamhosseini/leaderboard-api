<?php

namespace GameLadder\Factory;

use GameLadder\Config\Config;
use GameLadder\Repository\DatabasePlayerRepository;
use GameLadder\Repository\PlayerRepositoryInterface;
use GameLadder\Service\LeaderboardServiceInterface;
use GameLadder\Service\LoggerService;
use GameLadder\Service\RedisLeaderboardService;
use GameLadder\Service\RedisRecoveryService;
use PDO;
use Predis\Client;

class ServiceFactory
{
    private static ?PDO $pdo = null;
    private static ?Client $redis = null;

    public static function createLeaderboardService(): LeaderboardServiceInterface
    {
        return new RedisLeaderboardService(
            self::getRedisClient(),
            self::createPlayerRepository(),
            LoggerService::getLogger()
        );
    }

    public static function createPlayerRepository(): PlayerRepositoryInterface
    {
        return new DatabasePlayerRepository(self::getPdo());
    }

    public static function createRecoveryService(): RedisRecoveryService
    {
        return new RedisRecoveryService(
            self::getRedisClient(),
            self::createPlayerRepository(),
            LoggerService::getLogger()
        );
    }

    private static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', '127.0.0.1'),
                Config::get('DB_PORT', '3306'),
                Config::get('DB_NAME', 'leaderboard_db')
            );

            self::$pdo = new PDO(
                $dsn,
                Config::get('DB_USER', 'root'),
                Config::get('DB_PASS', 'root'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$pdo;
    }

    private static function getRedisClient(): Client
    {
        if (self::$redis === null) {
            self::$redis = new Client([
                'host' => Config::get('REDIS_HOST', '127.0.0.1'),
                'port' => Config::getInt('REDIS_PORT', 6379),
                'database' => Config::getInt('REDIS_DB', 0),
            ]);
        }

        return self::$redis;
    }
}

