<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Features\Rewards\Contracts\Ports\Input\CaptureCpaContextPort;
use App\Features\Rewards\DTOs\CaptureCpaContextData;
use App\Features\Rewards\DTOs\CaptureCpaContextResultData;
use App\Features\Rewards\Listeners\Kafka\AuthAccountRegisteredTopicHandler;
use Junges\Kafka\Contracts\ConsumerMessage;
use Tests\TestCase;

final class AuthAccountRegisteredTopicHandlerTest extends TestCase
{
    public function test_it_ignores_registration_without_ib_user(): void
    {
        $capture = new class implements CaptureCpaContextPort
        {
            public ?CaptureCpaContextData $captured = null;

            public function execute(CaptureCpaContextData $data): CaptureCpaContextResultData
            {
                $this->captured = $data;

                return new CaptureCpaContextResultData('00000000-0000-7000-8000-000000000001', true);
            }
        };
        config()->set('rewards.auth_account_registered.allowed_sources', ['crm_mmtech-demo_auth_user']);

        (new AuthAccountRegisteredTopicHandler($capture, config()))->handle($this->message(['event_name' => 'auth.account.registered', 'schema_version' => '1.0', 'source' => 'crm_mmtech-demo_auth_user', 'payload' => ['user_id' => 'bcf98eac-beee-49d5-9b94-7baf12f757bc', 'ib_user_id' => null]]));

        self::assertNull($capture->captured);
    }

    public function test_it_maps_only_required_identifiers_and_uses_reception_time(): void
    {
        $capture = new class implements CaptureCpaContextPort
        {
            public ?CaptureCpaContextData $captured = null;

            public function execute(CaptureCpaContextData $data): CaptureCpaContextResultData
            {
                $this->captured = $data;

                return new CaptureCpaContextResultData('00000000-0000-7000-8000-000000000001', true);
            }
        };
        config()->set('rewards.auth_account_registered.allowed_sources', ['crm_mmtech-demo_auth_user']);
        (new AuthAccountRegisteredTopicHandler($capture, config()))->handle($this->message(['event_name' => 'auth.account.registered', 'schema_version' => '1.0', 'source' => 'crm_mmtech-demo_auth_user', 'payload' => ['user_id' => 'bcf98eac-beee-49d5-9b94-7baf12f757bc', 'ib_user_id' => '00000000-0000-7000-8000-000000000002', 'user_email' => 'private@example.test', 'created_at' => '2000-01-01T00:00:00+00:00']]));

        self::assertNotNull($capture->captured);
        self::assertSame('bcf98eac-beee-49d5-9b94-7baf12f757bc', $capture->captured->referred_user_id);
        self::assertSame('00000000-0000-7000-8000-000000000002', $capture->captured->ib_user_id);
        self::assertNotSame('2000-01-01T00:00:00+00:00', $capture->captured->captured_at);
    }

    /** @param array<string, mixed> $body */
    private function message(array $body): ConsumerMessage
    {
        return new class($body) implements ConsumerMessage
        {
            public function __construct(private readonly array $body) {}

            public function getKey(): mixed
            {
                return null;
            }

            public function getTopicName(): ?string
            {
                return 'auth.events.v1';
            }

            public function getPartition(): ?int
            {
                return 0;
            }

            public function getHeaders(): ?array
            {
                return [];
            }

            public function getMessageIdentifier(): string
            {
                return 'test';
            }

            public function getBody(): mixed
            {
                return $this->body;
            }

            public function getOffset(): ?int
            {
                return 0;
            }

            public function getTimestamp(): ?int
            {
                return null;
            }
        };
    }
}
