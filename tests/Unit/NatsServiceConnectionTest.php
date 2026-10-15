<?php

namespace NextDeveloper\Tests\Unit;

use Basis\Nats\Client;
use NextDeveloper\Events\Services\NatsService;
use PHPUnit\Framework\TestCase;

/**
 * NatsService reuses one connection per process. These tests drive refreshConnection() with a
 * fake client (no broker needed) to check when the connection is kept, pinged or replaced.
 */
class NatsServiceConnectionTest extends TestCase
{
    private function serviceWith(Client $client, float $connectedAgo, float $usedAgo, int $publishes): NatsService
    {
        $service = new NatsService();
        $now = microtime(true);

        $this->set($service, 'client', $client);
        $this->set($service, 'connectedAt', $now - $connectedAgo);
        $this->set($service, 'lastUsedAt', $now - $usedAgo);
        $this->set($service, 'publishCount', $publishes);

        return $service;
    }

    private function set(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }

    private function get(object $object, string $property): mixed
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);

        return $ref->getValue($object);
    }

    private function refresh(NatsService $service): void
    {
        $ref = new \ReflectionMethod($service, 'refreshConnection');
        $ref->setAccessible(true);
        $ref->invoke($service);
    }

    private function fakeClient(array $subscriptions = []): Client
    {
        $client = $this->createMock(Client::class);
        $client->method('getSubscriptions')->willReturn($subscriptions);

        return $client;
    }

    public function test_a_fresh_busy_connection_is_reused_untouched(): void
    {
        $client = $this->fakeClient();
        $client->expects($this->never())->method('ping');
        $client->expects($this->never())->method('disconnect');

        $service = $this->serviceWith($client, 5, 1, 10);
        $this->refresh($service);

        $this->assertSame($client, $this->get($service, 'client'));
    }

    public function test_an_idle_connection_is_pinged_and_kept_when_alive(): void
    {
        $client = $this->fakeClient();
        $client->expects($this->once())->method('ping')->willReturn(true);
        $client->expects($this->never())->method('disconnect');

        $service = $this->serviceWith($client, 30, 20, 10);
        $this->refresh($service);

        $this->assertSame($client, $this->get($service, 'client'));
    }

    public function test_an_idle_connection_that_fails_the_ping_is_dropped(): void
    {
        $client = $this->fakeClient();
        $client->method('ping')->willReturn(false);
        $client->expects($this->once())->method('disconnect');

        $service = $this->serviceWith($client, 30, 20, 10);
        $this->refresh($service);

        $this->assertNull($this->get($service, 'client'));
    }

    public function test_a_ping_that_throws_drops_the_connection(): void
    {
        $client = $this->fakeClient();
        $client->method('ping')->willThrowException(new \RuntimeException('socket closed'));
        $client->expects($this->once())->method('disconnect');

        $service = $this->serviceWith($client, 30, 20, 10);
        $this->refresh($service);

        $this->assertNull($this->get($service, 'client'));
    }

    public function test_an_old_connection_is_recycled(): void
    {
        $client = $this->fakeClient();
        $client->expects($this->once())->method('disconnect');

        $service = $this->serviceWith($client, 61, 1, 10);
        $this->refresh($service);

        $this->assertNull($this->get($service, 'client'));
    }

    public function test_a_connection_with_many_publishes_is_recycled(): void
    {
        $client = $this->fakeClient();
        $client->expects($this->once())->method('disconnect');

        $service = $this->serviceWith($client, 5, 1, 1000);
        $this->refresh($service);

        $this->assertNull($this->get($service, 'client'));
        $this->assertSame(0, $this->get($service, 'publishCount'));
    }

    public function test_a_connection_holding_subscriptions_is_never_recycled(): void
    {
        $client = $this->fakeClient([['name' => 'agent.vm.1.evt', 'sid' => 'abc']]);
        $client->expects($this->never())->method('ping');
        $client->expects($this->never())->method('disconnect');

        $service = $this->serviceWith($client, 600, 600, 5000);
        $this->refresh($service);

        $this->assertSame($client, $this->get($service, 'client'));
    }

    public function test_no_client_means_nothing_to_refresh(): void
    {
        $service = new NatsService();
        $this->refresh($service);

        $this->assertNull($this->get($service, 'client'));
    }
}
