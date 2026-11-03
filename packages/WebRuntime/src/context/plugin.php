<?php
// Config
declare(strict_types=1);
namespace WebRuntime\Context;

// Requires
use WebRuntime\Context\AbstractResponse;
use WebRuntime\Core\PluginApi;

// Enums
enum PluginExecutionHook: string {
    case BEFORE_EXECUTE_REQUEST = "BEFORE_EXECUTE_REQUEST";
    case AFTER_EXECUTE_REQUEST = "AFTER_EXECUTE_REQUEST";
}

// Abstract Classes
abstract class AbstractPlugin {
    abstract public function __construct(PluginApi $pluginApi);
    abstract public function register(array $config): PluginExecutionHook;
    abstract public function execute(PluginContext $pluginContext): ?AbstractResponse;
}

// Classes
final class PluginContext {
    public function __construct(
        public AbstractRequest $request,
        public ?AbstractResponse $response
    ){}
}
