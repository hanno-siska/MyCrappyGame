<?php
// Config
declare(strict_types=1);
namespace WebRuntime;

// Requires
require_once __DIR__."/src/context/constants.php";
require_once __DIR__."/src/context/errors.php";
require_once __DIR__."/src/services/debugger.php";
require_once __DIR__."/src/utilities/utilities.php";
require_once __DIR__."/src/context/http.php";
require_once __DIR__."/src/core/router/registrar.php";
require_once __DIR__."/src/core/router/executor.php";
require_once __DIR__."/src/core/router/display.php";
require_once __DIR__."/src/services/session.php";
require_once __DIR__."/src/context/plugin.php";
require_once __DIR__."/src/core/plugins.php";

use WebRuntime\Context\AbstractRequest;
use WebRuntime\Context\AbstractRequestConfig;
use WebRuntime\Context\DefaultRequestInvalidContentTypeException;
use WebRuntime\Context\FrameworkRequestConfig;
use WebRuntime\Context\HttpMethod;
use WebRuntime\Context\InvalidHttpMethodException;
use WebRuntime\Context\PluginContext;
use WebRuntime\Context\PluginExecutionHook;
use WebRuntime\Context\Request;
use WebRuntime\Context\RouteNotFoundException;
use WebRuntime\Context\ViewResponse;
use WebRuntime\Context\WebRuntimeException;
use WebRuntime\Core\PluginApi;
use WebRuntime\Core\PluginManager;
use WebRuntime\Core\Router\Display;
use WebRuntime\Core\Router\Executor;
use WebRuntime\Core\Router\Registrar;
use WebRuntime\Core\Router\RegistrarRoute;
use WebRuntime\Services\SessionCookieConfig;
use WebRuntime\Services\Debug;
use WebRuntime\Services\DebugConfig;
use WebRuntime\Services\DebugLevel;
use WebRuntime\Services\Session;

use const WebRuntime\Context\VIEW_FAILURE_FILE;
use const WebRuntime\Context\WEBRUNTIME_API_VERSION;

// Class
final class WebRuntime {
    public const VERSION = WEBRUNTIME_API_VERSION;
    private Registrar $registrar;
    private Executor $executor;
    private Display $display;
    private FrameworkRequestConfig $frameworkRequestConfig;
    private PluginManager $pluginManager;

    private array $pluginManagerRegistrationArray = [];
    private array $routeManualRegistrationArray = [];

    public function __construct(
        private ?string $baseUri = null,
        private array $viewsDirectories = [],
        private bool $sessionsActive = false,
        private SessionCookieConfig $sessionConfig = new SessionCookieConfig(),
        private DebugConfig $debugConfig = new DebugConfig(),
        private string $requestType = Request::class,
        private ?AbstractRequestConfig $requestObjectConfig = null,
        private string $errorPage = __DIR__."/resources/failurePage.php",
        private bool $pluginsActive = false
    ) {
        try {
            Debug::setup($this->debugConfig);
            Debug::in(DebugLevel::INFO, "Awaiting Framework...");

            Session::setupSelf($this->sessionsActive, $this->sessionConfig);

            $this->registrar = new Registrar();
            $this->executor = new Executor(fn($uri, $method) => $this->registrar->findRoute($uri, $method));
            $this->display = new Display(fn($uri) => $this->registrar->findFile($uri), $this->baseUri);
            $this->frameworkRequestConfig = new FrameworkRequestConfig(baseUri: $this->baseUri);

            $pluginApi = new PluginApi(registrar: $this->registrar);
            $this->pluginManager = new PluginManager($pluginApi, $this->pluginsActive);

            if (!is_subclass_of($this->requestType, AbstractRequest::class)) {
                throw new WebRuntimeException("Illegal class given, must be subclass of '".AbstractRequest::class."'");
            }

            $this->registrar->mapFile(uri: "/error", filePath: $this->errorPage, internalFile: true);
        } catch (\Throwable $e) {
            Debug::in(DebugLevel::ERROR, "Internal error, while initializing: {$e}");
            http_response_code(500);
            exit(1);
        }
    }

    public function laterInit(): void {
        foreach($this->routeManualRegistrationArray as $item) {
            if (!is_null($item["filePath"])) {
                $this->registrar->mapFile(uri: $item["uri"], filePath: $item["filePath"], internalFile: false);
            }

            $this->registrar->mapRoute(uri: $item["uri"], route: $item["route"] ?? RegistrarRoute::VIEW, method: $item["httpMethod"], overwrite: $item["overwrite"]);
        }

        foreach($this->pluginManagerRegistrationArray as $item) {
            $this->pluginManager->registerPlugin(pluginPath: $item["pluginPath"], pluginConfig: $item["pluginConfig"], throwOnPluginInitFailure: $item["throwOnPluginInitFailure"]);
        }
    }

    public function registerPlugin(string $pluginPath, array $pluginConfig = [], bool $throwOnPluginInitFailure = true): void {
        $this->pluginManagerRegistrationArray[] = ["pluginPath" => $pluginPath, "pluginConfig" => $pluginConfig, "throwOnPluginInitFailure" => $throwOnPluginInitFailure];
    }

    public function mapRoute(string $uri, ?\Closure $route, HttpMethod $httpMethod, ?string $filePath = null, bool $overwrite = false): void {
        $this->routeManualRegistrationArray[] = ["uri" => $uri, "route" => $route, "httpMethod" => $httpMethod, "filePath" => $filePath, "overwrite" => $overwrite];
    }

    public function setAutoRouteExceptions(array $exceptions): void {
        $this->registrar->setAutoRouteExceptions($exceptions);
    }

    public function execute(): void {
        try {
            foreach($this->viewsDirectories as $directory) {
                $this->registrar->registerFiles($directory);
            }
            $this->laterInit();
        } catch (\Throwable $e) {
            Debug::in(DebugLevel::ERROR, "Internal error, while initializing: {$e}");
            http_response_code(500);
            exit(1);
        }

        try {
            Debug::in(DebugLevel::INFO, "Finished loading, handling request...");

            Session::startSession();

            $request = $this->requestType::createSelf(frameworkConfig: $this->frameworkRequestConfig, config: $this->requestObjectConfig);
            Debug::formatIn(DebugLevel::DEBUG, "WebRuntime", "execute", "Created & using Request '".$request::class."'");
            Debug::formatIn(DebugLevel::DEBUG, "WebRuntime", "execute", "Requested {$request->method->value} {$request->uri}");

            $response = $this->pluginManager->executeHook(PluginExecutionHook::BEFORE_EXECUTE_REQUEST, new PluginContext(request: $request, response: null));
            if (is_null($response)) {
                $response = $this->executor->getResponse($request);
            }
            $possibleResponse = $this->pluginManager->executeHook(PluginExecutionHook::AFTER_EXECUTE_REQUEST, new PluginContext(request: $request, response: $response));
            if (!is_null($possibleResponse)) {$response = $possibleResponse;}
        } catch(RouteNotFoundException) {
            $response = new ViewResponse(VIEW_FAILURE_FILE, 404, ["error" => "404, Page not found"]);
        } catch(InvalidHttpMethodException) {
            $response = new ViewResponse(VIEW_FAILURE_FILE, 405, ["error" => "405, Method not allowed"]);
        } catch(DefaultRequestInvalidContentTypeException) {
            $response = new ViewResponse(VIEW_FAILURE_FILE, 415, ["error" => "415, Default request, doesn't support sent content type"]);
        } catch(\Throwable $e) {
            $response = new ViewResponse(VIEW_FAILURE_FILE, 500, ["error" => "500, Internal server error"]);
        } finally {
            if ($e ?? false) {Debug::in(DebugLevel::WARNING, "Internal error, while handling request: {$e}");}
            if (!is_null($response ?? null)) {
                $this->display->sendResponse($response);
            } else {Debug::in(DebugLevel::ERROR, "Fatal failure response is null!!!"); exit(1);}
        }

        Debug::in(DebugLevel::INFO, "Finished request handling");
        exit(0);
    }
}
