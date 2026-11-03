<?php
// Declare
declare(strict_types=1);
namespace JsGame\Server;

// Requires
use WebRuntime\Context\HttpMethod;
use WebRuntime\Context\Request;
use const JsGame\ROOT;

// Classes
final class LeaderBoard {
    private static string $databasePath = ROOT."/game.db";
    private static ?\PDO $pdo = null;

    private static function db(): \PDO {
        $databaseNotExists = false;
        if (!file_exists(self::$databasePath)) {$databaseNotExists = true;}

        if (is_null(self::$pdo)) {
            $pdo = new \PDO("sqlite:".self::$databasePath);
            $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            self::$pdo = $pdo;

            if (is_null($databaseNotExists)) {
                $pdo->exec("
                CREATE TABLE Leaderboard(
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE NOT NULL,
                    wave INTEGER UNSIGNED NOT NULL DEFAULT 0
                );
                ");
            }
        }
        return self::$pdo;
    }

    public static function update(Request $request) {}

    public static function add(Request $request) {}

    public static function get(Request $request) {}
}

// Routes
$wrt->mapRoute("/api/leaderboard", fn($request) => LeaderBoard::get($request), HttpMethod::GET);
$wrt->mapRoute("/api/leaderboard", fn($request) => LeaderBoard::add($request), HttpMethod::POST);
$wrt->mapRoute("/api/leaderboard/:username", fn($request) => LeaderBoard::update($request), HttpMethod::PATCH);
