<?php

use App\Jobs\CloudSmokeJob;
use App\Mail\CloudSmokeMail;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('private', ['serve' => true]);
    Storage::fake('public_media', ['url' => 'http://localhost/storage']);

    // Serve each smoke file's contents back, as a bucket would, unless a test sets another response.
    $this->bucketResponse = null;
    Http::fake(fn (Request $request) => $this->bucketResponse ?? Http::response(
        'Shoreline cloud smoke test '.Str::between($request->url(), 'cloud-smoke/', '.txt'),
    ));

    // Keep the SMTP reachability probe off the network: nothing listens on port 1.
    config(['mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
});

it('passes when both disks, the queue, and mail work', function () {
    Mail::fake();

    $this->artisan('app:cloud-smoke', ['--mail' => 'owner@example.com'])
        ->expectsOutputToContain('Storage: private')
        ->expectsOutputToContain('Ran on')
        ->expectsOutputToContain('Sent to owner@example.com')
        ->assertSuccessful();

    Mail::assertSent(CloudSmokeMail::class, fn (CloudSmokeMail $mail) => $mail->hasTo('owner@example.com'));
    Http::assertSentCount(2);
    Storage::disk('private')->assertDirectoryEmpty('cloud-smoke');
    Storage::disk('public_media')->assertDirectoryEmpty('cloud-smoke');
});

it('fails when no queue worker runs the job', function () {
    Queue::fake();
    Sleep::fake();

    $this->artisan('app:cloud-smoke', ['--wait' => 0])
        ->expectsOutputToContain('No worker ran the test job within 0s')
        ->assertFailed();

    Queue::assertPushed(CloudSmokeJob::class);
});

it('fails when a stored file cannot be fetched by its URL', function () {
    $this->bucketResponse = Http::response('Forbidden', 403);

    $this->artisan('app:cloud-smoke')
        ->expectsOutputToContain('answered HTTP 403')
        ->assertFailed();
});

it('reports the mail error from the worker', function () {
    Mail::shouldReceive('to->send')->andThrow(new RuntimeException('Connection refused'));

    $this->artisan('app:cloud-smoke', ['--mail' => 'owner@example.com'])
        ->expectsOutputToContain('RuntimeException: Connection refused')
        ->assertFailed();
});
