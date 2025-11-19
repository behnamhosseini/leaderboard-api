<?php

namespace GameLadder\Service;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Psr\Log\LoggerInterface;

class LoggerService
{
    private static ?LoggerInterface $logger = null;

    public static function getLogger(): LoggerInterface
    {
        if (self::$logger === null) {
            $logger = new Logger('leaderboard');
            
            $logDir = __DIR__ . '/../../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logPath = $logDir . '/leaderboard.log';
            $handler = new RotatingFileHandler($logPath, 7, Logger::DEBUG);
            $logger->pushHandler($handler);
            
            $stderrHandler = new StreamHandler('php://stderr', Logger::ERROR);
            $logger->pushHandler($stderrHandler);
            
            self::$logger = $logger;
        }

        return self::$logger;
    }
}

