<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use App\Exceptions\AIServiceException;
use Throwable;

class PythonAIClient
{
    protected string $baseUrl;
    protected ?string $token;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ai_service.base_url'), '/');
        $this->token = config('services.ai_service.internal_token');
        $this->timeout = (int) config('services.ai_service.timeout', 15);
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->withHeaders([
                'X-Internal-Token' => $this->token ?? '',
            ]);
    }

    public function health(): array
    {
        return $this->postOrGet('get', '/internal/v1/health');
    }

    public function extractCv(array $payload): array
    {
        return $this->postOrGet('post', '/internal/v1/cv/extract', $payload);
    }

    public function scoreMatching(array $payload): array
    {
        return $this->postOrGet('post', '/internal/v1/matching/score', $payload);
    }

    protected function postOrGet(string $method, string $uri, array $data = []): array
    {
        try {
            $response = $method === 'get'
                ? $this->client()->get($uri, $data)
                : $this->client()->post($uri, $data);

            $response->throw();
            return $response->json('data', []);
        } catch (RequestException|Throwable $e) {
            throw new AIServiceException('AI_SERVICE_ERROR: ' . $e->getMessage(), $e);
        }
    }
}
