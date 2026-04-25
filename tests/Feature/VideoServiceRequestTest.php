<?php

use App\Models\User;
use App\Notifications\VideoServiceRequestNotification;
use Illuminate\Support\Facades\Notification;

function validRequestData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Juan Pérez',
        'email' => 'juan@example.com',
        'phone' => '+573001234567',
        'venue_address' => 'Cancha Sintética Los Pinos, Cra 7 #45',
        'preferred_date' => now()->addWeek()->format('Y-m-d'),
        'preferred_time' => '19:00',
        'selected_plan' => 'partido_pro',
        'message' => 'Necesitamos grabar nuestro próximo partido.',
    ], $overrides);
}

it('can submit a valid video service request as guest', function (): void {
    Notification::fake();

    $this->postJson(route('video-service-request.store'), validRequestData())
        ->assertCreated()
        ->assertJson(['message' => 'Solicitud enviada exitosamente.']);

    $this->assertDatabaseHas('video_service_requests', [
        'name' => 'Juan Pérez',
        'email' => 'juan@example.com',
        'club_name' => 'Anónimo',
        'status' => 'pending',
    ]);
});

it('uses authenticated user data when logged in', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('video-service-request.store'), validRequestData([
            'name' => null,
            'email' => null,
        ]))
        ->assertCreated();

    $this->assertDatabaseHas('video_service_requests', [
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]);
});

it('validates required fields for guests', function (): void {
    Notification::fake();

    $this->postJson(route('video-service-request.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'phone', 'venue_address', 'preferred_date', 'preferred_time', 'selected_plan']);

    Notification::assertNothingSent();
});

it('validates email format', function (): void {
    Notification::fake();

    $this->postJson(route('video-service-request.store'), validRequestData(['email' => 'not-an-email']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates selected plan must be valid', function (): void {
    Notification::fake();

    $this->postJson(route('video-service-request.store'), validRequestData(['selected_plan' => 'invalid']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['selected_plan']);
});

it('sends notification on successful submission', function (): void {
    Notification::fake();

    $this->postJson(route('video-service-request.store'), validRequestData())
        ->assertCreated();

    Notification::assertSentOnDemand(VideoServiceRequestNotification::class);
});

it('is throttled to 3 requests per minute', function (): void {
    Notification::fake();

    for ($i = 0; $i < 3; $i++) {
        $this->postJson(route('video-service-request.store'), validRequestData())
            ->assertCreated();
    }

    $this->postJson(route('video-service-request.store'), validRequestData())
        ->assertTooManyRequests();
});
