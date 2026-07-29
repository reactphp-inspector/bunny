<?php

declare(strict_types=1);

namespace ReactInspector\Tests\Bunny;

use ArrayObject;
use Bunny\Message;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function assert;

final class BunnyInstrumentationTest extends AsyncTestCase
{
    private ScopeInterface $scope;
    /** @var ArrayObject<int, ImmutableSpan> */
    private ArrayObject $storage;
    private TracerProvider $tracerProvider;
    private ChannelStub $channel;

    #[Before]
    public function resetBeforeNextTest(): void
    {
        $this->storage        = new ArrayObject();
        $this->tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(
                new InMemoryExporter($this->storage),
            ),
        );
        $this->scope          = Configurator::create()
            ->withTracerProvider($this->tracerProvider)
            ->withPropagator(new TraceContextPropagator())
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
}
