<?php
// Config
declare(strict_types=1);
namespace WebRuntime\Core\Router;

// Requires
use WebRuntime\Utilities\Helpers;
use WebRuntime\Context\HttpMethod;
use WebRuntime\Context\RegistrarException;
use WebRuntime\Services\Debug;
use WebRuntime\Services\DebugLevel;

use const WebRuntime\Context\REGISTRAR_INTERNAL_FILE_PREFIX;

// Classes
enum RegistrarRoute: string {
    case VIEW = "VIEW";
}

final class Registrar {
    private const SPECIAL_ENDINGS = ["php", "html"];
    private array $autoRouteExceptions = [];

    private array $routes = [];
    private array $fileMapper = [];

    public function findFile(string $uri): string|null {
        return $this->fileMapper[$uri] ?? null;
    }

    public function findRoute(string $uri, HttpMethod $method): array|null {
        $initial = $this->routes[$uri."::".$method->value] ?? null;
        if (!is_null($initial)) {return ["uri" => $uri, "values" => [], "child" => $initial];}

        return $this->extFindRoute($uri, $method);
    }

    private function extFindRoute(string $uri, HttpMethod $method): array|null {
        $splitIncomingUri = array_values(array_filter(explode("/", $uri)));
        $lenIncomingUri = count($splitIncomingUri);

        $possibleCandidates = [];
        foreach($this->routes as $candidate => $_) {
            $intermediate = explode("::", $candidate);

            $cleanedCandidate = array_values(array_filter(explode("/", $intermediate[0])));
            $lenCandidate = count($cleanedCandidate);

            if ($lenIncomingUri === $lenCandidate && HttpMethod::tryFrom($intermediate[1]) === $method) {
                $possibleCandidates[] = $cleanedCandidate;
            }
        }

        if (!$possibleCandidates) {return null;}
        $success = false;
        $bestCandidates = [];
        foreach($possibleCandidates as $pCandidate) {
            $candidateLiterals = 0;
            foreach($pCandidate as $idx => $seg) {
                if (!str_starts_with($seg, ":")) {$candidateLiterals += 1;}
                if ($seg !== $splitIncomingUri[$idx] && !str_starts_with($seg, ":")) {$success = false; break;}
                $success = true;
            }
            if ($success) {
                $bestCandidates[] = ["literals" => $candidateLiterals, "candidate" => $pCandidate];
                $success = false;
            }
        }

        $winner = [];
        foreach($bestCandidates as $potential) {
            if (($winner["literals"] ?? -1) < $potential["literals"]) {
                $winner = $potential;
            }
        }

        $extValues = [];
        foreach($winner["candidate"] ?? [] as $idx => $seg) {
            if ($seg === $splitIncomingUri[$idx]) {continue;}
            $extValues[substr($seg, 1)] = $splitIncomingUri[$idx];
        }

        if (is_null($winner["candidate"] ?? null)) {return null;}
        array_unshift($winner["candidate"], "");
        return ["uri" => implode("/", $winner["candidate"]), "values" => $extValues, "child" => $this->routes[implode("/", $winner["candidate"])."::".$method->value] ?? null];
    }

    public function mapFile(string $uri, string $filePath, bool $internalFile = false): void {
        if ($internalFile) {$mappingString = REGISTRAR_INTERNAL_FILE_PREFIX.$uri;}
        $this->fileMapper[$mappingString ?? $uri] = $filePath;
        Debug::formatIn(DebugLevel::DEBUG, "Registrar", "mapFile", "Mapped".($internalFile ? " [internal] ".REGISTRAR_INTERNAL_FILE_PREFIX : " ")."{$uri} -> {$filePath}");
    }

    public function mapRoute(string $uri, RegistrarRoute|\Closure $route, HttpMethod $method, bool $overwrite = false): void {
        if (!$overwrite && !is_null($this->findRoute($uri, $method))) {
            throw new RegistrarException("Cannot overwrite {$method->value} {$uri} because overwrite is disabled");
        }

        $this->routes[$uri."::".$method->value] = $route;
        Debug::formatIn(DebugLevel::DEBUG, "Registrar", "mapRoute", ($overwrite ? 'Overwrote' : 'Registered')." {$method->value} {$uri} as ".($route instanceof \Closure ? "CLOSURE" : "VIEW"));
        Debug::stateIn("registeredRoutes", ["$uri::$method->value" => ["uri" => $uri, "method" => $method->value, "route" => $route]]);
    }

    public function setAutoRouteExceptions(array $exceptions): void {
        $this->autoRouteExceptions = $exceptions;
    }

    public function registerFiles(string $folderPath): void {
        if (!is_dir($folderPath)) {
            throw new RegistrarException("Cannot register '{$folderPath}', isn't a directory");
        }

        $mappedFiles = 0;
        Debug::formatIn(DebugLevel::INFO, "Registrar", "registerFiles", "Scanning '{$folderPath}'");
        foreach (Helpers::walkdir($folderPath) as $path) {
            $relativePath = substr($path, strlen($folderPath));
            $pathInfo = pathinfo($relativePath);

            if (!in_array($pathInfo["extension"], self::SPECIAL_ENDINGS, true)) {continue;}
            $mappedFiles += 1;

            if ($pathInfo["filename"] == "index") {$filename = "/";} else {$filename = "/".$pathInfo["filename"];}

            $uri = "/".ltrim(str_replace(["\\", "."], ["/", ""], $pathInfo["dirname"]).$filename, "/");

            if (!in_array($uri, $this->autoRouteExceptions)) {
                $this->mapRoute($uri, RegistrarRoute::VIEW, HttpMethod::GET);
            } else {Debug::formatIn(DebugLevel::DEBUG, "Registrar", "registerFiles", "Route {$uri} in Route registration exceptions list, will not be registered as route, but can be accessed as file");}
            $this->mapFile($uri, $path);
        }
        Debug::formatIn(DebugLevel::INFO, "Registrar", "registerFiles", "Discovered & scanned in {$mappedFiles} unique file(s)");
    }
}
