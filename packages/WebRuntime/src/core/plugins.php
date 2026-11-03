<?php
// Config
declare(strict_types=1);
namespace WebRuntime\Core;

// Requires

use WebRuntime\Context\AbstractPlugin;
use WebRuntime\Context\AbstractResponse;
use WebRuntime\Context\HttpMethod;
use WebRuntime\Context\PluginContext;
use WebRuntime\Context\PluginException;
use WebRuntime\Context\PluginExecutionHook;
use WebRuntime\Core\Router\Registrar;
use WebRuntime\Services\Debug;
use WebRuntime\Services\DebugLevel;

use const WebRuntime\Context\WEBRUNTIME_PLUGIN_API_VERSION;

// Classes
final class PluginApi {
    public const VERSION = WEBRUNTIME_PLUGIN_API_VERSION;
    public function __construct(
        private Registrar $registrar
    ) {}

    public function routeExists(string $uri, ?HttpMethod $httpMethod = null): bool {
        if ($httpMethod) {return !is_null($this->registrar->findRoute(uri: $uri, method: $httpMethod));}
        foreach(HttpMethod::cases() as $method) {
            $result = !is_null($this->registrar->findRoute(uri: $uri, method: $method));
            if ($result) {return $result;}
        }
        return false;
    }
}

final class PluginManager {
    private array $pluginRegistry = [];

    public function __construct(
        private PluginApi $pluginApi,
        private bool $active
    ) {
        Debug::stateIn("pluginsActive", $this->active);
    }

    public function registerPlugin(string $pluginPath, array $pluginConfig = [], bool $throwOnPluginInitFailure = true): void {
        if (!$this->active) {throw new PluginException("Plugins disabled");}
        if (!file_exists($pluginPath)) {throw new PluginException("Plugin path '{$pluginPath}', not found");}
        $pluginClassType = include $pluginPath;

        if (!is_subclass_of($pluginClassType, AbstractPlugin::class)) {throw new PluginException("Illegal class given, must be subclass of '".AbstractPlugin::class."'");}
        try {
            $plugin = new $pluginClassType($this->pluginApi);
            $hook = $plugin->register($pluginConfig);
            $this->pluginRegistry[$hook->value][$pluginClassType] = $plugin;
            Debug::formatIn(DebugLevel::DEBUG, "PluginManager", "registerPlugin", "Registered {$pluginClassType} from {$pluginPath}");
            Debug::stateIn("registeredPlugins", [$pluginClassType => ["pluginPath" => $pluginPath, "pluginHook" => $hook]]);
        } catch (\Throwable $e) {
            Debug::formatIn(DebugLevel::WARNING, "PluginManager", "registerPlugin", "Failed to register {$pluginClassType} from '{$pluginPath}' {$e}");
            Debug::stateIn("failedPlugins", [$pluginClassType => $pluginPath]);
            if ($throwOnPluginInitFailure) {
                throw new PluginException("Failed to register {$pluginClassType} from '{$pluginPath}' {$e}");
            }
        }
    }

    public function executeHook(PluginExecutionHook $hook, PluginContext $context): ?AbstractResponse {
        if (!$this->active) {return null;}
        foreach ($this->pluginRegistry[$hook->value] ?? [] as $key => $value) {
            Debug::formatIn(DebugLevel::DEBUG, "PluginManager", "executeHook", "Attempting to execute plugin '{$key}'");
            $response = $value->execute($context);
            if (!is_null($response)) {return $response;}
        }
        return null;
    }
}
