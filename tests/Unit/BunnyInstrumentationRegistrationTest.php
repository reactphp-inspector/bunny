<?php

declare(strict_types=1);

namespace ReactInspector\Tests\Bunny\Unit;

use ArrayObject;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReactInspector\Bunny\BunnyInstrumentation;
use ReactInspector\Tests\Bunny\ChannelStub;

final class BunnyInstrumentationRegistrationTest extends TestCase
{
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function register(): void
    {
        /** @var ArrayObject<int, mixed> $storage */
        $storage        = new ArrayObject();
        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(
                new InMemoryExporter($storage),
            ),
        );

        $scope = Configurator::create()
            ->withTracerProvider($tracerProvider)
            ->withPropagator(TraceContextPropagator::getInstance())
            ->activate();

        try {
            BunnyInstrumentation::register();

            $channel = new ChannelStub();
            $channel->publish('body', [], '', 'routing-key');

            self::assertGreaterThanOrEqual(1, $storage->count());
        } finally {
            $scope->detach();
        }
    }
}
