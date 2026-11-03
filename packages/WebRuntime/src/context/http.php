<?php
// Config
declare(strict_types=1);
namespace WebRuntime\Context;

use Override;
use WebRuntime\Services\Debug;
use WebRuntime\Services\DebugLevel;

// Enums
enum HttpMethod: string {
    case GET = "GET";
    case POST = "POST";
    case PUT = "PUT";
    case PATCH = "PATCH";
    case DELETE = "DELETE";
}

// Abstract Classes
abstract class AbstractResponse {
    public function __construct(
        public int $httpCode,
        public array $httpHeaders = []
    ) {}

    protected function sendHeaders(): void {
        if ($this->httpHeaders && array_is_list($this->httpHeaders)) {
            throw new InvalidHeaderException("Invalid httpHeaders list passed in, must be an associative array");
        }

        http_response_code($this->httpCode);
        foreach ($this->httpHeaders as $key => $value) {
            if (str_contains($key, ":")) {
                throw new InvalidHeaderException("Header name (key: {$key}) must not contain ':', provide the name and value separately");
            }

            header($key.": ".$value);
        }
    }

    abstract public function sendResponse(array $sendContext): void;
}

abstract class AbstractRequestConfig {}
abstract class AbstractRequest {
    readonly public string $uri;
    readonly public HttpMethod $method;

    final public function __construct(?string $baseUri = null) {
        $parsedUrl = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);
        if (!is_null($baseUri) && str_starts_with($parsedUrl, rtrim($baseUri, "/")."/")) {$this->uri = substr($parsedUrl, strlen(rtrim($baseUri, "/")));}
        else {$this->uri = $parsedUrl;}

        try {
            $this->method = HttpMethod::from($_SERVER["REQUEST_METHOD"]);
        } catch (\ValueError $e) {throw new InvalidHttpMethodException(previous: $e);}
    }

    abstract public function insertUriStrings(array $uriStrings): void;
    abstract protected function setupSelf(?AbstractRequestConfig $config = null): void;
    abstract static public function createSelf(FrameworkRequestConfig $frameworkConfig, ?AbstractRequestConfig $config = null): static;
}

// Classes
final class FrameworkRequestConfig {
    public function __construct(
        public ?string $baseUri = null
    ) {}
}

class Request extends AbstractRequest {
    public array $body;
    public array $uriStrings = [];
    public array $queryStrings;

    private function parse(string $body, ?string $contentType): array {
        if (!$contentType) {return [];}
        $data = [];

        if ($contentType === "application/x-www-form-urlencoded") {
            parse_str($body, $data);
        } else if ($contentType === "application/json") {
            try {
                $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    $data[] = $decoded;
                } else {
                    $data = $decoded;
                }
            } catch (\Throwable) {
                Debug::formatIn(DebugLevel::WARNING, "Request", "parse", "The received json threw an error, continuing with []");
            }
        } else {
            throw new DefaultRequestInvalidContentTypeException("Unsupported content-type, supports: 'application/json' or 'application/x-www-form-urlencoded'");
        }

        return $data;
    }

    #[Override]
    public function insertUriStrings(array $uriStrings): void {
        $this->uriStrings = $uriStrings;
    }

    #[\Override]
    protected function setupSelf(?AbstractRequestConfig $config = null): void {
        $this->body = $this->parse(file_get_contents("php://input"), $_SERVER["CONTENT_TYPE"] ?? null);
        $this->queryStrings = $_GET;
    }

    #[\Override]
    public static function createSelf(FrameworkRequestConfig $frameworkConfig, ?AbstractRequestConfig $config = null): static {
        $class = new static($frameworkConfig->baseUri);
        $class->setupSelf($config);
        return $class;
    }
}

class ViewResponse extends AbstractResponse {
    public function __construct(
        public string $uri,

        int $httpCode,
        public array $data = [],
        array $httpHeaders = []
    ) {
        parent::__construct($httpCode, $httpHeaders);
    }

    #[\Override]
    public function sendResponse(array $sendContext): void {
        $file = $sendContext["findFile"]($this->uri);
        if (is_null($file) || !is_file($file)) {
            throw new RouteNotFoundException("Route '{$this->uri}', not found");
        }

        parent::sendHeaders();
        $data = $this->data;
        include $file;
    }
}

class JsonResponse extends AbstractResponse {
    public function __construct(
        public array $body,

        int $httpCode,
        array $httpHeaders = []
    ) {
        parent::__construct($httpCode, array_merge($httpHeaders, ["Content-Type" => "application/json"]));
    }

    #[\Override]
    public function sendResponse(array $sendContext): void {
        parent::sendHeaders();

        $encodedValues = json_encode($this->body);
        if ($encodedValues === false) {
            throw new ResponseException("Error while encoding self");
        }
        echo($encodedValues);
    }
}

class RedirectResponse extends AbstractResponse {
    public function __construct(
        public string $uri,

        int $httpCode,
        array $httpHeaders = []
    ) {
        parent::__construct($httpCode, $httpHeaders);
    }

    #[\Override]
    public function sendResponse(array $sendContext): void {
        parent::sendHeaders();
        header("Location: ".($sendContext["baseUri"] ?? "").$this->uri, true, $this->httpCode);
    }
}
