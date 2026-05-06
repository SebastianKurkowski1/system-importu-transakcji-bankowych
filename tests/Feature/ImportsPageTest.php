<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_page_can_be_rendered(): void
    {
        $this->get('/')->assertOk();
    }
}
