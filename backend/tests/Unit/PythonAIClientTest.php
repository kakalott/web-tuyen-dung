<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Clients\PythonAIClient;

class PythonAIClientTest extends TestCase
{
    public function test_python_ai_client_instantiation(): void
    {
        $client = new PythonAIClient();
        $this->assertInstanceOf(PythonAIClient::class, $client);
    }
}
