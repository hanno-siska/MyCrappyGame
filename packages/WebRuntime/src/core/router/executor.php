<?php
// Config
declare(strict_types=1);
namespace WebRuntime\Core\Router;

// Requires
use WebRuntime\Context\AbstractRequest;
use WebRuntime\Context\InvalidResponse;
use WebRuntime\Context\AbstractResponse;
use WebRuntime\Context\RouteNotFoundException;
use WebRuntime\Context\ViewResponse;
use WebRuntime\Services\Debug;
use WebRuntime\Services\DebugLevel;

// Classes
final class Executor {
    public function __construct(
        private \Closure $findRoute
    ){}

    private function runTask(AbstractRequest $request, \Closure $closure): AbstractResponse {
        $taskResult = $closure($request);
        if (!$taskResult instanceof AbstractResponse) {throw new InvalidResponse("Illegal return type, expected 'Response'");}
        return $taskResult;
    }

    public function getResponse(AbstractRequest &$request): AbstractResponse {
        $route = ($this->findRoute)($request->uri, $request->method);
        if (is_null($route) || is_null($route["child"])) {throw new RouteNotFoundException("Route '{$request->uri}', not found");}

        $request->insertUriStrings($route["values"]);
        Debug::formatIn(DebugLevel::INFO, "Executor", "getResponse", "Requested {$request->method->value} route '{$request->uri}' found AS '{$route["uri"]}'");

        if ($route["child"] instanceof \Closure) {
            Debug::formatIn(DebugLevel::DEBUG, "Executor", "getResponse", "Attempting to execute route closure...");
            return $this->runTask($request, $route["child"]);
        }

        return new ViewResponse($route["uri"], 200);
    }
}
