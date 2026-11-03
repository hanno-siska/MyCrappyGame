<?php
// Config
declare(strict_types=1);
namespace WebRuntime\Services;

// Enums & Classes
enum DebugLevel: string {
    case INFO = "Info   ";
    case WARNING = "Warning";
    case DEBUG = "Debug  ";
    case ERROR = "Error  ";

    public function getPriority(): int {
        return match($this) {
            self::ERROR => 0,
            self::WARNING => 1,
            self::INFO => 2,
            self::DEBUG => 3
        };
    }
}

final class DebugConfig {
    public function __construct(
        public bool $active = false,
        public bool $keepHistory = false,
        public bool $displayHistoryOnErrorPage = false,
        public DebugLevel $globalMinimumDebugLevel = DebugLevel::DEBUG,
        public mixed $writableStream = null
    ) {}
}

final class Debug {
    private static DebugConfig $config;
    private static array $history = [];
    private static array $stateHistory = [];

    public static function setup(DebugConfig $config): void {
        self::$config = $config;

        self::stateIn("active", self::$config->active);
        self::stateIn("keepHistory", self::$config->keepHistory);
        self::stateIn("displayHistory", self::$config->displayHistoryOnErrorPage);
    }

    public static function in(DebugLevel $level, string $message): void {
        if (!self::$config->active) {return;}
        if ($level->getPriority() > self::$config->globalMinimumDebugLevel->getPriority()) {return;}

        if (is_null(self::$config->writableStream)) {
            self::$config->writableStream = fopen("php://stderr", "w");
            self::formatIn(DebugLevel::DEBUG, "Debug", "in", "No stream passed in, using default STDERR");
        }

        $timestamp = date("H:i:s", time());
        $format = "[WebRuntime | $level->value | $timestamp] ".$message."\n";
        fwrite(self::$config->writableStream, $format);
        fflush(self::$config->writableStream);

        if (self::$config->keepHistory) {self::$history[] = $format;}
    }

    public static function stateIn(string $key, mixed $value): void {
        if (!self::$config->keepHistory) {return;}
        if (is_array($value)) {
            $value = array_merge(self::$stateHistory[$key] ?? [], $value);
        }
        self::$stateHistory[$key] = $value;
    }

    public static function formatIn(DebugLevel $level, string $className, string $functionName, string $message): void {
        self::in($level, "{$className}[{$functionName}]: {$message}");
    }

    public static function getHistory(): array {return self::$history;}
    public static function getStateHistory(): array {return self::$stateHistory;}
}
