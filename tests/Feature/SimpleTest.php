<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

class SimpleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Debug: check if app is created
        $app = $this->createApplication();
        $router = $app->make('Illuminate\Routing\Router');
        $routes = $router->getRoutes();
        $uri = null;
        foreach ($routes as $r) {
            $u = (string) $r->uri();
            if ($u === '/') {
                $uri = $u;
                break;
            }
        }
        file_put_contents('/tmp/test_debug.txt', "SetUp: Routes: " . $routes->count() . " URI: " . ($uri ?? 'not found') . PHP_EOL, FILE_APPEND);
    }

    public function test_simple_hello(): void
    {
        // Debug: check the response
        $response = $this->get('/');
        file_put_contents('/tmp/test_debug2.txt', "Test: Status: " . $response->status() . PHP_EOL, FILE_APPEND);
        
        $this->assertEquals(200, $response->status());
    }
}