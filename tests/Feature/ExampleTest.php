<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        foreach (['/', '/editions', '/archive', '/about', '/contribute', '/up'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_hq_requires_editorial(): void
    {
        $member = User::factory()->create(['username' => 'm1']);
        $this->actingAs($member)->get('/hq')->assertForbidden();

        config(['redstar.admin_usernames' => 'boss']);
        $admin = User::factory()->create(['username' => 'boss']);
        $this->actingAs($admin)->get('/hq')->assertOk();
    }

    public function test_member_can_submit(): void
    {
        $u = User::factory()->create(['username' => 'w1']);
        $this->actingAs($u)->post('/submissions', ['title' => 'Hello', 'kind' => 'poem', 'body' => 'Lines'])->assertRedirect();
        $this->assertDatabaseHas('submissions', ['title' => 'Hello', 'user_id' => $u->id]);
    }
}
