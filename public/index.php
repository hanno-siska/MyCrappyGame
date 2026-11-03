<?php
// Declares
declare(strict_types=1);
namespace JsGame;

define("JsGame\ROOT", dirname(__DIR__));

// Requires
require_once ROOT."/packages/WebRuntime/WebRuntime.php";
use WebRuntime\WebRuntime;

// Setup
$wrt = new WebRuntime(
    viewsDirectories: [ROOT."/views"]
);

// Routes
require_once ROOT."/src/server.php";

$wrt->execute();
