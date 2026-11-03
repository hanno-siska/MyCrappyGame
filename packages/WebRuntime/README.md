<div>
  <img src="./resources/images/icon.svg" alt="WebRuntime" height="64" style="vertical-align: middle;">
  <span style="font-size: 32px; font-weight: 700; vertical-align: middle; margin-left: 12px;">
    WebRuntime
  </span>
</div>

![version](https://img.shields.io/badge/WebRuntime_Version-0.4.6-blue)
![version](https://img.shields.io/badge/Plugin_Api_Version-0.1.0-blue)
---
A lightweight PHP framework for websites, including sessions, file-based routing, plugins, and more.

## Requirements & Setup
- PHP 8.4+
- PHP Sessions (Optional)

### Setup
1. Clone the repository
2. Add to your PHP file: `require_once "your_path/WebRuntime.php"`
3. See the Usage Example below
4. Run PHP's built-in server using: `php -S 127.0.0.1:8080`

## Usage Examples
### Minimal Usage Example
```php
<?php
require_once "your_path/WebRuntime.php";
use WebRuntime\WebRuntime;

$webruntime = new WebRuntime(
  viewsDirectories: ["pathToYourFolder"], // Your directory of .html and .php files
  // ... additional configuration options
);

$webruntime->execute();
```

### Adding Actions to Routes
```php
<?php
require_once "your_path/WebRuntime.php";
use WebRuntime\WebRuntime;
use WebRuntime\Context\HttpMethod;

$webruntime = new WebRuntime(
  viewsDirectories: ["pathToYourFolder"], // Your directory of .html and .php files
  // ... additional configuration options
);

$webruntime->mapRoute("/uri", fn($request) => functionDoes($request), HttpMethod::GET, overwrite: true) //Maps a closure to a URI and HTTP method. Set overwrite: true to replace an existing route. Use filePath when creating a new route that should also map to a file.

$webruntime->execute();
```

### Simple Plugin Example
```php
<?php
require_once "./../WebRuntime.php";

use WebRuntime\Context\AbstractPlugin;
use WebRuntime\Context\PluginContext;
use WebRuntime\Context\AbstractResponse;
use WebRuntime\Context\PluginExecutionHook;
use WebRuntime\Core\PluginApi;
use WebRuntime\Services\Debug;
use WebRuntime\Services\DebugLevel;

class TestPlugin extends AbstractPlugin {
    #[\Override]
    public function __construct(public PluginApi $pluginApi) {}

    #[\Override]
    public function register(array $config): PluginExecutionHook {
        return PluginExecutionHook::BEFORE_EXECUTE_REQUEST;
    }

    #[\Override]
    public function execute(PluginContext $pluginContext): ?AbstractResponse{
      Debug::formatIn(DebugLevel::DEBUG, "TestPlugin", "execute", "YAY, i was executed");
      return null;
    }
}

return TestPlugin::class;
```
How to load plugins:
```php
$webruntime->registerPlugin("pathToYourFile", throwOnPluginInitFailure: true, pluginConfig: ["something" => "smth"]); //Assoc array of configuration options
```


### Core Routing Rules
The router only scans .html and .php files.
- `index.html`/`index.php` -> `/`
- `about.html` -> `/about`
- `user/profile.php` -> `/user/profile`
