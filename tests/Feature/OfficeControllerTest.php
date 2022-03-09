<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;

use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function isListsAllOfficesPaginatedWay()
    {
        Office::factory(3)->create();
        $response = $this->get('/api/offices');
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $this->assertNotNull($response->json('data')[0]['id']);
        $this->assertNotNull($response->json('meta'));
        $this->assertNotNull($response->json('links'));
    }

    /**
     * @test
     */
    public function isOnlyListsOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory(3)->create();
        Office::factory(1)->create(['hidden' => true]);
        Office::factory(1)->create(['approval_status' => Office::APPROVAL_PENDING]);

        $response = $this->get('/api/offices');
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    /**
     * @test
     */
    public function isFiltersByHostId()
    {
        Office::factory(3)->create();
        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();
        $response = $this->get('/api/offices?host_id=' . $host->id);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function isFiltersByUserId()
    {
        Office::factory(3)->create();

        $user = User::factory()->create();
        $office = Office::factory()->create();
        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();
        $response = $this->get('/api/offices?user_id=' . $user->id);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function itIncludesImagesTagsAndUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        Image::factory()->for($office, 'resource')->create();

        $response = $this->get('/api/offices');
        $response->assertOk();

        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertCount(1, $response->json('data')[0]['images']);
        $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
    }
}
