<?php

namespace GameLadder\Config;

use Dotenv\Dotenv;

class Config
{
    private static ?array $config = null;

    public static function load(): void
    {
        if (self::$config !== null) {
            return;
        }

        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        self::$config = $_ENV;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        return self::$config[$key] ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int)self::get($key, (string)$default);
    }
}

