<?php

use App\Models\User;
use App\Models\PremiumServiceOrder;

test('anyone can submit a premium service order', function () {
    $response = $this->post('/premium-service-orders', [
        'name' => 'Test Client',
        'email' => 'client@test.com',
        'phone' => '+8801712345678',
        'service_type' => 'custom_feature',
        'budget' => '500_1500',
        'description' => 'I need a custom bKash payment gateway integrated into my dashboard.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('premium_service_orders', [
        'name' => 'Test Client',
        'email' => 'client@test.com',
        'phone' => '+8801712345678',
        'service_type' => 'custom_feature',
        'budget' => '500_1500',
        'description' => 'I need a custom bKash payment gateway integrated into my dashboard.',
        'status' => 'pending',
    ]);
});

test('submitting premium service order validation rules', function () {
    $response = $this->post('/premium-service-orders', [
        'name' => '',
        'email' => 'invalid-email',
        'service_type' => '',
        'description' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'service_type', 'description']);
});

test('unauthenticated users are redirected from premium service order management endpoints', function () {
    $this->get('/premium-service-orders')->assertRedirect('/login');
    $this->patch('/premium-service-orders/1', ['status' => 'contacted'])->assertRedirect('/login');
    $this->delete('/premium-service-orders/1')->assertRedirect('/login');
});

test('authenticated user can view premium service orders', function () {
    $user = User::factory()->create(['is_superadmin' => true]);
    $order = PremiumServiceOrder::create([
        'name' => 'Pending Client',
        'email' => 'pending@client.com',
        'service_type' => 'deployment_setup',
        'description' => 'Deploy on AWS VPS.',
    ]);

    $response = $this->actingAs($user)->get('/premium-service-orders');

    $response->assertStatus(200);
});

test('authenticated user can update premium service order status', function () {
    $user = User::factory()->create(['is_superadmin' => true]);
    $order = PremiumServiceOrder::create([
        'name' => 'Pending Client',
        'email' => 'pending@client.com',
        'service_type' => 'deployment_setup',
        'description' => 'Deploy on AWS VPS.',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->patch("/premium-service-orders/{$order->id}", [
        'status' => 'contacted',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('premium_service_orders', [
        'id' => $order->id,
        'status' => 'contacted',
    ]);
});

test('authenticated user can delete premium service order', function () {
    $user = User::factory()->create(['is_superadmin' => true]);
    $order = PremiumServiceOrder::create([
        'name' => 'Pending Client',
        'email' => 'pending@client.com',
        'service_type' => 'deployment_setup',
        'description' => 'Deploy on AWS VPS.',
    ]);

    $response = $this->actingAs($user)->delete("/premium-service-orders/{$order->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('premium_service_orders', [
        'id' => $order->id,
    ]);
});
