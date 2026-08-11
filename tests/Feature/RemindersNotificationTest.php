<?php

use App\Models\User;
use App\Models\Category;
use App\Models\RecurringSchedule;
use App\Models\Loan;
use App\Models\Client;
use App\Models\License;
use App\Services\FirebaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;

it('updates user fcm token successfully', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('profile.fcm-token'), [
            'fcm_token' => 'test-fcm-token',
        ]);

    $response->assertRedirect();
    expect($user->fresh()->fcm_token)->toBe('test-fcm-token');
});

it('sends notifications for due reminders via command', function () {
    // 1. Arrange data
    $user = User::factory()->create(['fcm_token' => 'fake-device-token']);
    
    // Create due recurring schedule
    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'type' => 'expense',
        'color' => '#ff0000',
    ]);
    
    $schedule = RecurringSchedule::create([
        'user_id' => $user->id,
        'type' => 'expense',
        'frequency' => 'weekly',
        'amount' => 50.00,
        'start_date' => Carbon::today(),
        'next_due_date' => Carbon::today(),
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    // Create due loan
    $loan = Loan::create([
        'user_id' => $user->id,
        'person_name' => 'John Doe',
        'type' => 'lent',
        'amount' => 100.00,
        'due_date' => Carbon::today(),
        'status' => 'active',
    ]);

    // Create due license
    $client = Client::create([
        'user_id' => $user->id,
        'name' => 'Acme Inc',
        'saas_name' => 'Acme Cloud',
    ]);
    
    $license = License::create([
        'user_id' => $user->id,
        'client_id' => $client->id,
        'amount' => 200.00,
        'billing_cycle' => 'monthly',
        'next_renewal_date' => Carbon::today()->addDays(2), // within 7 days
        'status' => 'active',
    ]);

    // 2. Mock Firebase Service
    $this->mock(FirebaseService::class, function (MockInterface $mock) {
        // Should receive 3 notifications (schedule, loan, license)
        $mock->shouldReceive('sendPushNotification')
            ->times(3)
            ->andReturn(true);
    });

    // 3. Act
    $exitCode = Artisan::call('reminders:send-notifications');

    // 4. Assert
    expect($exitCode)->toBe(0);
});
