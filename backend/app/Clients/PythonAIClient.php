<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AIServiceException;
use Throwable;

class PythonAIClient
{
    protected string $baseUrl;
    protected ?string $token;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ai_service.base_url', config('services.ai_service.url', 'http://127.0.0.1:8001')), '/');
        $this->token = config('services.ai_service.internal_token', config('services.ai_service.token'));
        $this->timeout = (int) config('services.ai_service.timeout', 30);
    }

    public function health(): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson()
                ->withHeaders(['X-Internal-Token' => $this->token ?? ''])
                ->get('/internal/v1/health');

            $response->throw();
            return $response->json();
        } catch (Throwable $e) {
            throw new AIServiceException('AI_SERVICE_ERROR: ' . $e->getMessage(), $e);
        }
    }

    public function extractCv(UploadedFile $file, array $catalog): array
    {
        $url = $this->baseUrl . '/internal/v1/cv/extract';

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Internal-Token' => $this->token ?? ''
                ])->attach(
                    'file', file_get_contents($file->getRealPath()), $file->getClientOriginalName()
                )->post($url, [
                    'skill_catalog' => json_encode($catalog)
                ]);

            if ($response->failed()) {
                Log::error('AI_SERVICE_ERROR', ['status' => $response->status(), 'body' => $response->body()]);
                throw new AIServiceException('AI_SERVICE_ERROR: status ' . $response->status());
            }

            return $response->json();
        } catch (AIServiceException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('AI_SERVICE_ERROR', ['message' => $e->getMessage()]);
            throw new AIServiceException('AI_SERVICE_ERROR: ' . $e->getMessage(), $e);
        }
    }

    public function scoreMatching(array $payload): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson()
                ->withHeaders(['X-Internal-Token' => $this->token ?? ''])
                ->post('/internal/v1/matching/score', $payload);

            $response->throw();
            return $response->json('data', []);
        } catch (Throwable $e) {
            throw new AIServiceException('AI_SERVICE_ERROR: ' . $e->getMessage(), $e);
        }
    }
}
