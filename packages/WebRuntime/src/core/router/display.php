<?php
// Config
declare(strict_types=1);
namespace WebRuntime\Core\Router;

// Requires
use WebRuntime\Context\AbstractResponse;
use WebRuntime\Context\RouteNotFoundException;
use WebRuntime\Context\ViewResponse;
use WebRuntime\Services\Debug;
use WebRuntime\Services\DebugLevel;

use const WebRuntime\Context\VIEW_FAILURE_FILE;

// Classes
final class Display {
    public function __construct(
        private \Closure $findFile,
        private ?string $baseUri
    ) {}

    private function sendCreateError(int $httpCode): null|AbstractResponse {
        $file = ($this->findFile)(VIEW_FAILURE_FILE);
        if (is_null($file) || !is_file($file)) {
            Debug::formatIn(DebugLevel::ERROR, "Display", "sendCreateError", "Fatal failure, while resolving error view");
            http_response_code($httpCode);
            echo("{$httpCode}, Something went terribly wrong");
            return null;
        }

        return new ViewResponse(VIEW_FAILURE_FILE, 404, ["error" => "404, Page not found"]);
    }

    public function sendResponse(AbstractResponse $response): void {
        try {
            $response->sendResponse(["baseUri" => $this->baseUri, "findFile" => $this->findFile]);
            Debug::formatIn(DebugLevel::DEBUG, "Display", "sendResponse", "Sent response ".$response::class);
        } catch(RouteNotFoundException) {
            Debug::formatIn(DebugLevel::WARNING, "Display", "sendResponse", "Failed to resolve view, attempting error view");
            $result = $this->sendCreateError($response->httpCode);
            if (!is_null($result)) {$this->sendResponse($result);}
        }
    }
}
