<?php

declare(strict_types=1);

namespace ReactInspector\Tests\Bunny\Integration;

use ArrayObject;
use Bunny\ChannelInterface;
use Bunny\Client;
use Bunny\ClientInterface;
use Bunny\Configuration;
use Bunny\Defaults;
use Bunny\Message;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function array_key_exists;
use function assert;
use function getenv;
use function is_array;
use function is_string;
use function ltrim;
use function parse_url;
use function uniqid;

#[Group('functional')]
final class BunnyInstrumentationTest extends AsyncTestCase
{
    private const string EXCHANGE_NAME = 'test_exchange';

    private ScopeInterface $scope;
    /** @var ArrayObject<int, SpanDataInterface> */
    private ArrayObject $storage;

    #[Before]
    public function createScope(): void
    {
        $this->storage  = new ArrayObject();
        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(
                new InMemoryExporter($this->storage),
            ),
        );

        $this->scope = Configurator::create()
            ->withTracerProvider($tracerProvider)
            ->withPropagator(TraceContextPropagator::getInstance())
            ->activate();
    }

    #[After]
    public function detach(): void
    {
        $this->scope->detach();
    }

    #[Test]
    public function publishWithoutArgsWorks(): void
    {
        [$client, $routingKey, $channel] = $this->setUpQueue();

        try {
            $channel->publish(
                body: 'test',
                exchange: self::EXCHANGE_NAME,
                routingKey: $routingKey,
            );

            self::assertCount(1, $this->storage);

            $span = $this->storage[0];
            assert($span instanceof ImmutableSpan);

            self::assertNotEmpty($span->getInstrumentationScope()->getVersion());
            self::assertSame(self::EXCHANGE_NAME . ' ' . $routingKey . ' publish', $span->getName());
            /** @phpstan-ignore classConstant.deprecatedInterface */
            self::assertSame('amqp', $span->getAttributes()->get(TraceAttributes::MESSAGING_SYSTEM));
            self::assertSame(SpanKind::KIND_PRODUCER, $span->getKind());
            self::assertSame(self::EXCHANGE_NAME . ' ' . $routingKey, $span->getAttributes()->get('messaging.destination_publish.name'));
            self::assertSame('topic', $span->getAttributes()->get('messaging.destination.kind'));

            $envelope = $channel->get(
                queue: $routingKey,
            );
            self::assertInstanceOf(Message::class, $envelope);
        } finally {
            $channel->queuePurge($routingKey);
            $client->disconnect();
        }
    }

    #[Test]
    #[DataProvider('channelMessageOperationsProvider')]
    public function channelMessageOperations(string $messageInteraction): void
    {
        [$client, $routingKey, $channel] = $this->setUpQueue();

        try {
            $channel->publish(
                body: 'test',
                headers: [],
                exchange: self::EXCHANGE_NAME,
                routingKey: $routingKey,
            );

            self::assertCount(1, $this->storage);

            $publishSpan = $this->storage[0];
            assert($publishSpan instanceof ImmutableSpan);

            self::assertNotEmpty($publishSpan->getInstrumentationScope()->getVersion());
            self::assertSame(self::EXCHANGE_NAME . ' ' . $routingKey . ' publish', $publishSpan->getName());
            /** @phpstan-ignore classConstant.deprecatedInterface */
            self::assertSame('amqp', $publishSpan->getAttributes()->get(TraceAttributes::MESSAGING_SYSTEM));
            self::assertSame(SpanKind::KIND_PRODUCER, $publishSpan->getKind());
            self::assertSame(self::EXCHANGE_NAME . ' ' . $routingKey, $publishSpan->getAttributes()->get('messaging.destination_publish.name'));
            self::assertSame('topic', $publishSpan->getAttributes()->get('messaging.destination.kind'));

            $envelope = $channel->get(
                queue: $routingKey,
            );
            self::assertInstanceOf(Message::class, $envelope);

            self::assertTrue($envelope->hasHeader('traceparent'), 'traceparent header is missing');

            /** @phpstan-ignore method.dynamicName */
            $channel->{$messageInteraction}($envelope);

            self::assertCount(2, $this->storage);

            $interactionSpan = $this->storage[1];
            assert($interactionSpan instanceof ImmutableSpan);
            self::assertSame(SpanKind::KIND_CLIENT, $interactionSpan->getKind());
            self::assertSame($routingKey . ' ' . $messageInteraction, $interactionSpan->getName());
        } finally {
            $channel->queuePurge($routingKey);
            $client->disconnect();
        }
    }

    /** @return iterable<array{0: string}> */
    public static function channelMessageOperationsProvider(): iterable
    {
        yield 'ack' => ['ack'];
        yield 'nack' => ['nack'];
        yield 'reject' => ['reject'];
    }

    /** @return array{0: ClientInterface, 1: string, 2: ChannelInterface} */
    private function setUpQueue(): array
    {
        $routingKey = uniqid('test_queue_', true);

        $client  = $this->getRabbitConnection();
        $channel = $client->channel();
        $channel->queueDeclare($routingKey);
        $channel->exchangeDeclare(self::EXCHANGE_NAME, 'topic');
        $channel->queueBind(self::EXCHANGE_NAME, $routingKey, $routingKey);

        return [$client, $routingKey, $channel];
    }

    private function getRabbitConnection(): ClientInterface
    {
        $url = getenv('TEST_RABBITMQ_CONNECTION_URI');
        if (! is_string($url)) {
            self::fail('Expected RabbitMQ connection URL to be a string');
        }

        $uri = parse_url($url);
        if (! is_array($uri)) {
            self::fail('Expected parsed RabbitMQ connection URL to be an array');
        }

        return new Client(new Configuration(
            host: $uri['host'] ?? Defaults::HOST,
            port: $uri['port'] ?? Defaults::PORT,
            vhost: array_key_exists('path', $uri) ? ($uri['path'] === Defaults::VHOST ? $uri['path'] : ltrim($uri['path'], Defaults::VHOST)) : Defaults::VHOST,
            user: $uri['user'] ?? Defaults::USER,
            password: $uri['pass'] ?? Defaults::PASSWORD,
        ));
    }
}
