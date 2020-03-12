<?php

declare(strict_types=1);

namespace Cronofy\Http;

interface HttpRequest
{
    public function httpGet(string $url, array $auth_headers): array;

    public function getPage(string $url, array $auth_headers, string $url_params = ''): array;

    public function httpPost(string $url, array $params, array $auth_headers): array;

    public function httpDelete(string $url, array $params, array $auth_headers): array;
}
