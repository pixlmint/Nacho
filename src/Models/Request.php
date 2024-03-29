<?php

namespace Nacho\Models;

use Exception;
use Nacho\Contracts\RequestInterface;
use Nacho\Contracts\RouteInterface;
use Nacho\Helpers\ServerVarsParser;

class Request implements RequestInterface
{
    public string $requestMethod;
    public ?string $contentType;
    public array $headers;
    public array $body = [];
    protected ?Route $route = null;

    function __construct()
    {
        $this->bootstrapSelf();
    }

    public function setRoute(RouteInterface $route): void
    {
        $this->route = $route;
    }

    public function getRoute(): Route
    {
        if (!$this->route) {
            throw new Exception('Route has not yet been defined');
        }

        return $this->route;
    }

    private function bootstrapSelf(): void
    {
        foreach ($_SERVER as $key => $value) {
            $this->{$this->toCamelCase($key)} = $value;
        }
        $this->requestMethod = ServerVarsParser::getRequestMethod();
        $this->contentType = ServerVarsParser::getContentType();
        $this->body = [];
        $this->headers = ServerVarsParser::parseHeaders();
    }

    private function toCamelCase($string): array|string
    {
        $result = strtolower($string);

        preg_match_all('/_[a-z]/', $result, $matches);

        foreach ($matches[0] as $match) {
            $c = str_replace('_', '', strtoupper($match));
            $result = str_replace($match, $c, $result);
        }

        return $result;
    }

    public function getFiles(): array
    {
        return $_FILES;
    }

    public function getBody(): array
    {
        if ($this->body) {
            return $this->body;
        }
        $unsafe = [];
        if ($this->requestMethod === HttpMethod::GET) {
            $unsafe = $_GET;
        }

        if ($this->requestMethod === HttpMethod::POST) {
            $unsafe = $_POST;
        }

        if (in_array($this->requestMethod, [HttpMethod::PUT, HttpMethod::DELETE])) {
            $requestContent = file_get_contents("php://input");
            if ($this->contentType === 'application/json') {
                $unsafe = json_decode($requestContent, true);
            } else {
                parse_str($requestContent, $unsafe);
            }
        }
        $this->body = $this->filterArrayDeep($unsafe);

        return $this->body;
    }

    private function filterArrayDeep(array $arr): array
    {
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $arr[$key] = $this->filterArrayDeep($value);
            } else {
                $value = str_replace('<script>', '&lt;script&gt;', $value);
                $value = str_replace('</script>', '&lt;/script&gt;', $value);
                $arr[$key] = $value;
            }
        }
        return $arr;
    }
}
