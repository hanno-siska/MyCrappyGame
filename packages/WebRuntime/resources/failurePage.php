<?php
use WebRuntime\Services\Debug;
use WebRuntime\Services\Session;

use const WebRuntime\Context\WEBRUNTIME_API_VERSION;
use const WebRuntime\Context\WEBRUNTIME_PLUGIN_API_VERSION;

$debugStates = Debug::getStateHistory();
$sessionStates = Session::getSessionState();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <style>
        @view-transition {
            navigation: auto;
        }

        * {
            box-sizing: border-box;
            padding: 0;
            margin: 0;
            color: rgb(26, 26, 27);
            font-family: monospace;
        }

        html, body {
            font-size: 16px;
        }

        header {
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: rgb(64, 139, 229);
            box-shadow: 2px 0px 10px black;
        }

        main {
            padding: 0.5rem;
        }

        h1 {
            font-size: 2rem;
        }

        .image-container {
            display: flex;
            align-items: center;
        }

        .image-container img {
            box-shadow: 3px 4px 10px rgb(43, 83, 133);
        }

        .debug-container {
            background-color: rgba(251, 255, 0, 0.15);
            padding: 0.2rem;
            border: 1px solid black;
        }
    </style>
</head>
<body>
    <header>
        <h1>Whoops, something went wrong.</h1>
        <div class="image-container">
            <img src="data:image/svg+xml;base64,<?= base64_encode(file_get_contents(__DIR__."/images/icon.svg")); ?>" alt="WebRuntime" width="32px" height="32px">
            <div>
                <p style="padding-left: 0.5rem;">WebRuntime Version: <?= WEBRUNTIME_API_VERSION ?></p>
                <?php if ($debugStates["displayHistory"] ?? false): ?>
                    <p style="padding-left: 0.5rem;">Plugin API Version: <?= WEBRUNTIME_PLUGIN_API_VERSION ?></p>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <main>
        <p><?= $data["error"] ?? "" ?></p>
        <?php if ($debugStates["displayHistory"] ?? false): ?>
            <br>
            <div class="debug-container">
                <p class="debug-container">PHP Version: <?= phpversion() ?></p>
                <p style="margin-top: 0.2rem" class="debug-container">Debug mode: <?= ($debugStates["active"] ? "enabled" : "disabled") ?></p>
                <p style="margin-top: 0.2rem; margin-bottom: 0.2rem" class="debug-container">Debug keep history: <?= ($debugStates["keepHistory"] ? "enabled" : "disabled") ?></p>
                <p class="debug-container">Debug display history on error page: <?= ($debugStates["displayHistory"] ? "enabled" : "disabled") ?></p>
                <div class="debug-container" style="margin-top: 0.2rem;">
                    <p class="debug-container">Sessions: <?= ($sessionStates["active"] ? "enabled" : "disabled") ?></p>
                    <?php if($sessionStates["active"]): ?>
                        <div class="debug-container" style="margin-top: 0.2rem;">
                            <?php foreach($sessionStates["cookieConfig"] as $key => $value): ?>
                                <?php
                                    if (is_bool($value)) {$value = ($value ? "true" : "false");}
                                    ?>
                                <p><?= $key ?> -> <?= $value ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="debug-container" style="margin-top: 0.2rem;">
                    <p class="debug-container">Plugins: <?= ($debugStates["pluginsActive"] ? "enabled" : "disabled") ?></p>
                    <?php if($debugStates["pluginsActive"]): ?>
                        <div class="debug-container" style="margin-top: 0.2rem;">
                            <?php if ($debugStates["registeredPlugins"] ?? false): ?>
                                <p>Enabled plugins:</p>
                                <div class="debug-container">
                                    <?php foreach($debugStates["registeredPlugins"] as $key => $value): ?>
                                        <p><?= $key ?>[<?= $value["pluginHook"]->value ?>] -> <?= $value["pluginPath"] ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($debugStates["failedPlugins"] ?? false): ?>
                                <p style="margin-top: 0.2rem;">Failed to load plugins:</p>
                                <div class="debug-container">
                                    <?php foreach($debugStates["failedPlugins"] as $key => $value): ?>
                                        <p><?= $key ?> -> <?= $value ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="debug-container" style="margin-top: 0.2rem;">
                    <div class="debug-container" style="margin-top: 0.2rem;">
                        <?php if ($debugStates["registeredRoutes"] ?? false): ?>
                            <p>Registered Routes:</p>
                            <div class="debug-container">
                                <?php foreach($debugStates["registeredRoutes"] as $controlKey => $item): ?>
                                    <p><?= $item["method"] ?? "Unknown" ?> <?= $item["uri"] ?> as <?= $item["route"]->value ?? "Closure" ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            <?php if($debugStates["keepHistory"]): ?>
                <br>
                <div class="debug-container">
                    <?php foreach(array_merge(Debug::getHistory(), ["[End of history, page sent]"]) as $history): ?>
                        <p style="white-space: pre-wrap;"><?= htmlspecialchars($history) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
