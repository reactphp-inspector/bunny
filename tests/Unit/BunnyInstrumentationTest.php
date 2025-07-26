<?php

declare(strict_types=1);

namespace ReactInspector\Tests\Bunny\Unit;

use ArrayObject;
use Bunny\Message;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReactInspector\Tests\Bunny\ChannelStub;
use RuntimeException;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function assert;

#[Group('unit')]
#[AllowMockObjectsWithoutExpectations]
final class BunnyInstrumentationTest extends AsyncTestCase
{
    private ScopeInterface $scope;
    /** @var ArrayObject<int, ImmutableSpan> */
    private ArrayObject $storage;
    private ChannelStub $channel;

    #[Before]
    public function resetBeforeNextTest(): void
    {
        $this->storage  = new ArrayObject();
        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(
                new InMemoryExporter($this->storage),
            ),
        );
        $this->scope    = Configurator::create()
            ->withTracerProvider($tracerProvider)
            ->withPropagator(TraceContextPropagator::getInstance())
            ->activate();

        $this->channel = new ChannelStub();
    }

    #[After]
    public function detachScopeAfterTests(): void
    {
        $this->scope->detach();
    }

    #[Test]
    public function publish(): void
    {
        self::assertCount(0, $this->storage);
        self::assertSame(1, $this->channel->publish('body', [], '', 'routing-key'));
        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame('routing-key publish', $span->getName());
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('amqp', $span->getAttributes()->get(TraceAttributes::MESSAGING_SYSTEM));
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('publish', $span->getAttributes()->get(TraceAttributes::MESSAGING_OPERATION_TYPE));
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('routing-key', $span->getAttributes()->get(TraceAttributes::MESSAGING_RABBITMQ_DESTINATION_ROUTING_KEY));
    }

    #[Test]
    public function publishWithExchange(): void
    {
        self::assertCount(0, $this->storage);
        self::assertSame(1, $this->channel->publish('body', [], 'test_exchange', 'routing-key'));
        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame('test_exchange routing-key publish', $span->getName());
        self::assertSame('topic', $span->getAttributes()->get('messaging.destination.kind'));
        self::assertSame('test_exchange routing-key', $span->getAttributes()->get('messaging.destination_publish.name'));
    }

    #[Test]
    public function consume(): void
    {
        self::assertCount(0, $this->storage);
        $this->channel->consume(static fn (): string => 'handled', 'test-queue');
        $this->channel->deliver(new Message('consumer-tag', 1, false, '', 'routing-key', [], 'body'));
        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame('test-queue consumer', $span->getName());
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('amqp', $span->getAttributes()->get(TraceAttributes::MESSAGING_SYSTEM));
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('receive', $span->getAttributes()->get(TraceAttributes::MESSAGING_OPERATION_TYPE));
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('routing-key', $span->getAttributes()->get(TraceAttributes::MESSAGING_RABBITMQ_DESTINATION_ROUTING_KEY));
    }

    #[Test]
    public function consumeRecordsExceptionOnFailure(): void
    {
        $this->channel->consume(static function (): never {
            throw new RuntimeException('consume failed');
        }, 'test-queue');

        try {
            $this->channel->deliver(new Message('consumer-tag', 1, false, '', 'routing-key', [], 'body'));
        } catch (RuntimeException) {
        }

        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame(RuntimeException::class, $span->getAttributes()->get(TraceAttributes::ERROR_TYPE));
    }

    #[Test]
    public function ack(): void
    {
        self::assertCount(0, $this->storage);
        $message = new Message('consumer-tag', 1, false, '', 'routing-key', [], 'body');
        $this->channel->ack($message);
        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame('routing-key ack', $span->getName());
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('amqp', $span->getAttributes()->get(TraceAttributes::MESSAGING_SYSTEM));
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('ack', $span->getAttributes()->get(TraceAttributes::MESSAGING_OPERATION_TYPE));
    }

    #[Test]
    public function nack(): void
    {
        self::assertCount(0, $this->storage);
        $message = new Message('consumer-tag', 1, false, '', 'routing-key', [], 'body');
        $this->channel->nack($message);
        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame('routing-key nack', $span->getName());
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('amqp', $span->getAttributes()->get(TraceAttributes::MESSAGING_SYSTEM));
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('nack', $span->getAttributes()->get(TraceAttributes::MESSAGING_OPERATION_TYPE));
    }

    #[Test]
    public function reject(): void
    {
        self::assertCount(0, $this->storage);
        $message = new Message('consumer-tag', 1, false, '', 'routing-key', [], 'body');
        $this->channel->reject($message);
        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame('routing-key reject', $span->getName());
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('amqp', $span->getAttributes()->get(TraceAttributes::MESSAGING_SYSTEM));
        /** @phpstan-ignore classConstant.deprecatedInterface */
        self::assertSame('reject', $span->getAttributes()->get(TraceAttributes::MESSAGING_OPERATION_TYPE));
    }

    #[Test]
    public function publishRecordsExceptionOnFailure(): void
    {
        $this->channel->throwOnNextCall = new RuntimeException('publish failed');

        try {
            $this->channel->publish('body', [], '', 'routing-key');
        } catch (RuntimeException) {
        }

        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        self::assertNotEmpty($span->getEvents());
    }

    #[Test]
    #[DataProvider('interactionMethodsProvider')]
    public function interactionRecordsExceptionOnFailure(string $method): void
    {
        $this->channel->throwOnNextCall = new RuntimeException($method . ' failed');
        $message                        = new Message('consumer-tag', 1, false, '', 'routing-key', [], 'body');

        try {
            /** @phpstan-ignore method.dynamicName */
            $this->channel->{$method}($message);
        } catch (RuntimeException) {
        }

        self::assertCount(1, $this->storage);
        $span = $this->storage->offsetGet(0);
        assert($span instanceof ImmutableSpan);
        self::assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        self::assertNotEmpty($span->getEvents());
    }

    #[Test]
    public function publishSkipsPostHookWhenScopeDetached(): void
    {
        $this->channel->detachContextBeforeReturn = true;

        self::assertSame(1, $this->channel->publish('body', [], '', 'routing-key'));
        self::assertCount(0, $this->storage);
    }

    #[Test]
    #[DataProvider('interactionMethodsProvider')]
    public function interactionSkipsPostHookWhenScopeDetached(string $method): void
    {
        $this->channel->detachContextBeforeReturn = true;
        $message                                  = new Message('consumer-tag', 1, false, '', 'routing-key', [], 'body');

        /** @phpstan-ignore method.dynamicName */
        $this->channel->{$method}($message);

        self::assertCount(0, $this->storage);
    }

    /** @return iterable<array{0: string}> */
    public static function interactionMethodsProvider(): iterable
    {
        yield 'ack' => ['ack'];
        yield 'nack' => ['nack'];
        yield 'reject' => ['reject'];
    }
}
