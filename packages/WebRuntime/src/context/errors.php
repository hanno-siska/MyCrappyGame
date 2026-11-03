<?php
// Config
declare(strict_types=1);
namespace WebRuntime\Context;

// Exceptions
class WebRuntimeException extends \Exception {}

class RegistrarException extends WebRuntimeException {}
class SessionException extends WebRuntimeException {}
class PluginException extends WebRuntimeException {}

class InvalidHttpMethodException extends WebRuntimeException {}
class DefaultRequestInvalidContentTypeException extends WebRuntimeException {}

class ResponseException extends WebRuntimeException {}
class InvalidHeaderException extends ResponseException {}
class InvalidResponse extends ResponseException {}
class RouteNotFoundException extends ResponseException {}
