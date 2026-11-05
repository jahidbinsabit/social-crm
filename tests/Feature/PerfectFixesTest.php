<?php

namespace Tests\Feature;

use App\Http\Middleware\InternalApiMiddleware;
use App\Models\Account;
use App\Models\Client;
use App\Models\Message;
use App\Models\User;
use App\Services\OutboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Perfect-fix regression tests (audit item #37).
 * Run on staging after `composer install` + test DB setup:
 *   php artisan test --filter=PerfectFixesTest
 */
class PerfectFixesTest extends TestCase
{
    use RefreshDatabase;

    /** #6 — internal API must fail closed when no secret is configured. */
    public function test_internal_middleware_fails_closed_without_secret(): void
    {
        config(['app.internal_secret' => '']);
        $request = Request::create('/api/internal/wa-incoming', 'POST');
        $response = (new InternalApiMiddleware())->handle($request, fn () => response()->json(['ok' => true]));
        $this->assertSame(503, $response->getStatusCode());
    }

    /** #6 — wrong secret is rejected, correct secret passes (hash_equals path). */
    public function test_internal_middleware_rejects_wrong_and_accepts_correct_secret(): void
    {
        config(['app.internal_secret' => 'test-secret-value']);
        $bad = Request::create('/api/internal/wa-incoming', 'POST', server: ['HTTP_X_INTERNAL_SECRET' => 'wrong']);
        $badResponse = (new InternalApiMiddleware())->handle($bad, fn () => response()->json(['ok' => true]));
        $this->assertSame(401, $badResponse->getStatusCode());

        $good = Request::create('/api/internal/wa-incoming', 'POST', server: ['HTTP_X_INTERNAL_SECRET' => 'test-secret-value']);
        $goodResponse = (new InternalApiMiddleware())->handle($good, fn () => response()->json(['ok' => true]));
        $this->assertSame(200, $goodResponse->getStatusCode());
    }

    /** #38 — the User->messages relation must use staff_id, not user_id. */
    public function test_user_messages_relation_uses_staff_id(): void
    {
        $relation = (new User())->messages();
        $this->assertSame('staff_id', $relation->getForeignKeyName());
    }

    /** #16 — outbound sending refuses a client/account platform mismatch. */
    public function test_outbound_service_rejects_platform_mismatch(): void
    {
        $account = new Account(['platform' => 'whatsapp']);
        $client = new Client(['platform' => 'telegram', 'identifier' => '12345']);
        $this->expectException(\InvalidArgumentException::class);
        app(OutboundMessageService::class)->send($account, $client, 'hello');
    }

    /** #3 — realtime events must broadcast on a PRIVATE channel. */
    public function test_new_message_event_uses_private_channel(): void
    {
        $message = new Message([
            'platform' => 'telegram', 'account_id' => 1, 'client_id' => 1,
            'sender_type' => 'client', 'message_type' => 'text', 'message_body' => 'hi',
        ]);
        $message->setRelation('client', new Client(['identifier' => '12345', 'display_name' => 'T']));
        $message->setRelation('account', new Account(['platform' => 'telegram']));
        $channels = (new \App\Events\NewMessageReceived($message))->broadcastOn();
        foreach ((array) $channels as $channel) {
            $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $channel,
                'admin-events and staff channels must be private');
        }
    }
}
