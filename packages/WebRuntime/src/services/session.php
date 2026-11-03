<?php
// Config
declare(strict_types=1);
namespace WebRuntime\Services;

// Requires
use WebRuntime\Context\SessionException;
use WebRuntime\Services\Debug;
use WebRuntime\Services\DebugLevel;

// Classes
final readonly class SessionCookieConfig {
    public function __construct(
        public int $lifetime = 0,
        public bool $http_only = true,
        public string $path = "/",
        public string $samesite = "Lax",
        public bool $secure = true
    ) {}

    public function to_array(): array {
        return [
            "lifetime" => $this->lifetime,
            "httponly" => $this->http_only,
            "path" => $this->path,
            "samesite" => $this->samesite,
            "secure" => $this->secure
        ];
    }
}

final class Session {
    private static bool $active = false;
    private static SessionCookieConfig $config;

    public static function getSessionState(): array {
        return ["active" => self::$active, "cookieConfig" => self::$config->to_array()];
    }

    private static function isEnabled(): void {
        if (!self::$active || session_status() === PHP_SESSION_DISABLED) {
            Debug::formatIn(DebugLevel::ERROR, "Session", "isEnabled", "Session active = '".(self::$active ? "true" : "false")."', or sessions are disabled in php");
            throw new SessionException("Sessions disabled");
        }
    }

    public static function setupSelf(bool $active, SessionCookieConfig $config): void {
        self::$active = $active;
        self::$config = $config;
    }

    public static function startSession(): void {
        if (!self::$active) {return;}
        self::isEnabled();

        if (session_status() !== PHP_SESSION_NONE) {return;}

        session_set_cookie_params(self::$config->to_array());
        session_start();

        Debug::formatIn(DebugLevel::DEBUG, "Session", "startSession", "Session created/continuing: '".session_id()."'");
    }

    public static function deleteSession(): void {
        self::isEnabled();

        if (session_status() === PHP_SESSION_NONE) {return;}

        session_unset();
        session_destroy();
        setcookie(session_name(), "", time() - 3600, "/");
    }

    public static function regenerateSession(): void {
        self::startSession();
        session_regenerate_id(true);
    }

    public static function setValue(string $key, mixed $value, bool $overwrite = false): void {
        self::isEnabled();

        if (key_exists($key, $_SESSION) && !$overwrite) {
            throw new SessionException("Cannot overwrite {$key} because overwrite is disabled");
        }

        $_SESSION[$key] = $value;
    }

    public static function getValue(string $key, mixed $defaultValue = null): mixed {
        self::isEnabled();

        return $_SESSION[$key] ?? $defaultValue;
    }

    public static function deleteValue(string $key): void {
        self::isEnabled();

        if (!key_exists($key, $_SESSION)) {return;}
        unset($_SESSION[$key]);
    }
}
