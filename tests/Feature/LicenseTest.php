<?php

use App\Models\User;
use App\Models\Client;
use App\Models\License;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;

test('unauthenticated users are redirected from license endpoints', function () {
    $this->get('/licenses')->assertRedirect('/login');
    $this->post('/clients', [])->assertRedirect('/login');
    $this->post('/licenses', [])->assertRedirect('/login');
});

test('user can register a client', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/clients', [
        'name' => 'BigCorp Inc',
        'email' => 'billing@bigcorp.com',
        'saas_name' => 'MetricsDashboard',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('clients', [
        'user_id' => $user->id,
        'name' => 'BigCorp Inc',
        'email' => 'billing@bigcorp.com',
        'saas_name' => 'MetricsDashboard',
    ]);
});

test('user can create a client license', function () {
    $user = User::factory()->create();
    $client = Client::create([
        'user_id' => $user->id,
        'name' => 'SaaSClient',
        'saas_name' => 'MySaaS',
    ]);

    $response = $this->actingAs($user)->post('/licenses', [
        'client_id' => $client->id,
        'amount' => 199.00,
        'billing_cycle' => 'monthly',
        'next_renewal_date' => Carbon::now()->addMonth()->format('Y-m-d'),
        'status' => 'active',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('licenses', [
        'user_id' => $user->id,
        'client_id' => $client->id,
        'amount' => 199.00,
        'billing_cycle' => 'monthly',
        'status' => 'active',
    ]);
});

test('logging payment advances renewal date and records income transaction', function () {
    $user = User::factory()->create();
    
    $client = Client::create([
        'user_id' => $user->id,
        'name' => 'ACME Group',
        'saas_name' => 'API Gateway',
    ]);

    $initialRenewalDate = Carbon::now()->startOfDay();

    $license = License::create([
        'user_id' => $user->id,
        'client_id' => $client->id,
        'amount' => 250.00,
        'billing_cycle' => 'monthly',
        'next_renewal_date' => $initialRenewalDate,
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->post("/licenses/{$license->id}/pay", [
        'amount' => 250.00,
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'advance_renewal' => true,
    ]);

    $response->assertRedirect();

    // 1. Verify license renewal date is advanced by 1 month
    $updatedLicense = License::find($license->id);
    expect($updatedLicense->next_renewal_date->format('Y-m-d'))->toBe($initialRenewalDate->copy()->addMonth()->format('Y-m-d'));
    expect($updatedLicense->last_paid_at->format('Y-m-d'))->toBe(Carbon::now()->format('Y-m-d'));

    // 2. Verify "SaaS License" category was created
    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'SaaS License',
        'type' => 'income',
    ]);

    $category = Category::where('user_id', $user->id)->where('name', 'SaaS License')->first();

    // 3. Verify transaction was logged with client_id and license_id
    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category_id' => $category->id,
        'client_id' => $client->id,
        'license_id' => $license->id,
        'amount' => 250.00,
        'type' => 'income',
        'description' => 'SaaS License Payment - ACME Group (API Gateway)',
    ]);
});

test('user can access licenses index with payments data', function () {
    $user = User::factory()->create();
    $client = Client::create([
        'user_id' => $user->id,
        'name' => 'ACME Group',
        'saas_name' => 'API Gateway',
    ]);

    $license = License::create([
        'user_id' => $user->id,
        'client_id' => $client->id,
        'amount' => 250.00,
        'billing_cycle' => 'monthly',
        'next_renewal_date' => Carbon::now(),
        'status' => 'active',
    ]);

    // Log a payment to generate a transaction
    $this->actingAs($user)->post("/licenses/{$license->id}/pay", [
        'amount' => 250.00,
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'advance_renewal' => true,
    ]);

    $response = $this->actingAs($user)->get('/licenses');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Licenses/Index')
        ->has('payments', 1)
        ->where('payments.0.client_id', $client->id)
        ->where('payments.0.amount', 250)
    );
});

test('user can log a partial payment to an account without advancing renewal date', function () {
    $user = User::factory()->create();
    $client = Client::create([
        'user_id' => $user->id,
        'name' => 'ACME Group',
        'saas_name' => 'API Gateway',
    ]);

    $account = App\Models\Account::create([
        'user_id' => $user->id,
        'name' => 'City Bank',
        'type' => 'bank',
        'initial_balance' => 1000.00,
    ]);

    $initialRenewalDate = Carbon::now()->startOfDay();

    $license = License::create([
        'user_id' => $user->id,
        'client_id' => $client->id,
        'amount' => 500.00,
        'billing_cycle' => 'monthly',
        'next_renewal_date' => $initialRenewalDate,
        'status' => 'active',
    ]);

    // Log a part payment of 200.00 to City Bank account without advancing renewal
    $response = $this->actingAs($user)->post("/licenses/{$license->id}/pay", [
        'amount' => 200.00,
        'account_id' => $account->id,
        'transaction_date' => Carbon::now()->format('Y-m-d'),
        'description' => 'First installment',
        'advance_renewal' => false,
    ]);

    $response->assertRedirect();

    // Verify license renewal date is NOT advanced
    $updatedLicense = License::find($license->id);
    expect($updatedLicense->next_renewal_date->format('Y-m-d'))->toBe($initialRenewalDate->format('Y-m-d'));
    expect($updatedLicense->last_paid_at->format('Y-m-d'))->toBe(Carbon::now()->format('Y-m-d'));

    // Verify transaction was logged with custom amount 200.00 and is mapped to account
    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'client_id' => $client->id,
        'license_id' => $license->id,
        'account_id' => $account->id,
        'amount' => 200.00,
        'type' => 'income',
        'description' => 'First installment',
    ]);

    // Verify account balance reflects the payment
    $dashboardResponse = $this->actingAs($user)->get('/dashboard');
    $accountsData = $dashboardResponse->original->getData()['page']['props']['accounts'];
    $bankData = collect($accountsData)->firstWhere('id', $account->id);
    expect($bankData['current_balance'])->toBe(1200.0);
});

test('user can update their client license', function () {
    $user = User::factory()->create();
    $client1 = Client::create([
        'user_id' => $user->id,
        'name' => 'SaaSClient',
        'saas_name' => 'MySaaS',
    ]);
    $client2 = Client::create([
        'user_id' => $user->id,
        'name' => 'AnotherClient',
        'saas_name' => 'OtherSaaS',
    ]);

    $license = License::create([
        'user_id' => $user->id,
        'client_id' => $client1->id,
        'amount' => 199.00,
        'billing_cycle' => 'monthly',
        'next_renewal_date' => Carbon::now()->addMonth()->format('Y-m-d'),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->patch("/licenses/{$license->id}", [
        'client_id' => $client2->id,
        'amount' => 299.50,
        'billing_cycle' => 'yearly',
        'next_renewal_date' => Carbon::now()->addYear()->format('Y-m-d'),
        'status' => 'inactive',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('licenses', [
        'id' => $license->id,
        'client_id' => $client2->id,
        'amount' => 299.50,
        'billing_cycle' => 'yearly',
        'status' => 'inactive',
    ]);
});

test('user cannot update another user\'s client license', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $client1 = Client::create([
        'user_id' => $user1->id,
        'name' => 'SaaSClient 1',
        'saas_name' => 'MySaaS 1',
    ]);
    $client2 = Client::create([
        'user_id' => $user2->id,
        'name' => 'SaaSClient 2',
        'saas_name' => 'MySaaS 2',
    ]);

    $license = License::create([
        'user_id' => $user1->id,
        'client_id' => $client1->id,
        'amount' => 199.00,
        'billing_cycle' => 'monthly',
        'next_renewal_date' => Carbon::now()->addMonth()->format('Y-m-d'),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user2)->patch("/licenses/{$license->id}", [
        'client_id' => $client2->id,
        'amount' => 299.50,
        'billing_cycle' => 'yearly',
        'next_renewal_date' => Carbon::now()->addYear()->format('Y-m-d'),
        'status' => 'inactive',
    ]);

    $response->assertStatus(403);
});
