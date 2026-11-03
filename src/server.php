<?php
// Declare
declare(strict_types=1);
namespace JsGame\Server;

// Requires
use WebRuntime\Context\HttpMethod;
use WebRuntime\Context\JsonResponse;
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

            if ($databaseNotExists) {
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

    public static function add(Request $request) {
        if (!key_exists("username", $request->body) || !key_exists("wave", $request->body)) {
            return new JsonResponse(["Malformed Request, missing: username or wave"], 400);
        }

        $db = self::db();
        try {
            $stmt = $db->prepare("INSERT INTO Leaderboard (username, wave) VALUES (:username, :wave)");
            $stmt->execute(["username" => $request->body["username"], "wave" => $request->body["wave"]]);
        } catch(\PDOException) {
            $stmt = $db->prepare("UPDATE Leaderboard SET wave = :wave WHERE username = :username AND wave < :wave;");
            $stmt->execute(["username" => $request->body["username"], "wave" => $request->body["wave"]]);
            return new JsonResponse(["Resource Updated"], 200);
        }

        return new JsonResponse(["Resource Created"], 201);
    }

    public static function get() {
        $db = self::db();
        $stmt = $db->prepare("SELECT username, wave FROM Leaderboard ORDER BY wave DESC;");
        $stmt->execute();
        return new JsonResponse($stmt->fetchAll(), 200);
    }
}

// Routes
$wrt->mapRoute("/api/leaderboard", fn() => LeaderBoard::get(), HttpMethod::GET);
$wrt->mapRoute("/api/leaderboard", fn($request) => LeaderBoard::add($request), HttpMethod::POST);
