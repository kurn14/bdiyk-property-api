<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\PropertyType;
use App\Models\Property;
use App\Models\Booking;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'password' => bcrypt('password123')
        ]);
    }

    public function test_admin_can_login()
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->adminUser->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['access_token', 'user']);
    }

    public function test_property_type_crud()
    {
        $token = $this->postJson('/api/login', [
            'email' => $this->adminUser->email,
            'password' => 'password123',
        ])->json('access_token');

        // Create
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/property-types', [
                 'name' => 'Bedroom',
                 'description' => 'A cozy bedroom'
             ]);

        $response->assertStatus(201);
        $typeId = $response->json('id');

        // Read
        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->getJson('/api/property-types')
             ->assertStatus(200)
             ->assertJsonFragment(['name' => 'Bedroom']);

        // Update
        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->putJson("/api/property-types/{$typeId}", [
                 'name' => 'Master Bedroom'
             ])
             ->assertStatus(200);

        // Delete
        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->deleteJson("/api/property-types/{$typeId}")
             ->assertStatus(200);
    }
    
    public function test_property_and_booking_dynamic_status()
    {
        $token = $this->postJson('/api/login', [
            'email' => $this->adminUser->email,
            'password' => 'password123',
        ])->json('access_token');

        $type = PropertyType::create(['name' => 'Meeting Room']);

        $propertyResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/properties', [
                 'property_type_id' => $type->id,
                 'name' => 'Room A',
                 'capacity' => 10,
                 'status' => 'available'
             ]);
             
        $propertyResponse->assertStatus(201);
        $propertyId = $propertyResponse->json('id');

        // Check initial status is available
        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->getJson("/api/properties/{$propertyId}")
             ->assertJson(['status' => 'available']);

        // Create an active booking
        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/bookings', [
                 'property_id' => $propertyId,
                 'contact_name' => 'John Doe',
                 'contact_email' => 'john@test.com',
                 'contact_phone' => '123456789',
                 'start_date' => now()->subHour()->toDateTimeString(),
                 'end_date' => now()->addHour()->toDateTimeString(),
                 'status' => 'in_use' // Or scheduled
             ])->assertStatus(201);

        // Check status changed to occupied dynamically
        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->getJson("/api/properties/{$propertyId}")
             ->assertJson(['status' => 'occupied']);
    }
}
