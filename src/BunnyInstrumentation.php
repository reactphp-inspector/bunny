<?php

declare(strict_types=1);

namespace ReactInspector\Bunny;

use Bunny\ChannelInterface;
use Bunny\Message as BunnyMessage;
use Composer\InstalledVersions;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorageScopeInterface;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\Version;
use Throwable;

use function assert;
use function is_string;
use function OpenTelemetry\Instrumentation\hook;
use function sprintf;

final class BunnyInstrumentation
{
    public const string NAME = 'bunny';

    /**
     * The name of the Composer package.
     *
     * @see https://getcomposer.org/doc/04-schema.md#name
     */
    private const string COMPOSER_NAME = 'react-inspector/bunny';

    /**
     * Name of this instrumentation library which provides the instrumentation for Bunny.
     *
     * @see https://opentelemetry.io/docs/specs/otel/glossary/#instrumentation-library
     */
    private const string INSTRUMENTATION_LIBRARY_NAME = 'io.opentelemetry.contrib.php.bunny';

    /** @phpstan-ignore shipmonk.deadMethod */
    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation(
            self::INSTRUMENTATION_LIBRARY_NAME,
            InstalledVersions::getPrettyVersion(self::COMPOSER_NAME),
            Version::VERSION_1_32_0->url(),
        );

        self::publish($instrumentation);
        self::consume($instrumentation);

        self::createInteractionWithQueueSpan($instrumentation, 'ack');
        self::createInteractionWithQueueSpan($instrumentation, 'nack');
    }

    private static function publish(CachedInstrumentation $instrumentation): void
    {
        hook(
            ChannelInterface::class,
            'publish',
            pre: static function (
                ChannelInterface $channel,
                array $params,
                string $class,
                string $function,
                string|null $filename,
                int|null $lineno,
            ) use ($instrumentation): array {
                // string $body, array $headers = [], string $exchange = '', string $routingKey = '', bool $mandatory = false, bool $immediate = false
                /** @var array<string, mixed> $headers */
                [$body, $headers, $exchange, $routingKey] = $params;
                assert(is_string($body));
                assert(is_string($exchange));
                assert(is_string($routingKey));

                $parentContext = Context::getCurrent();

                $spanBuilder = $instrumentation
                    ->tracer()
                    ->spanBuilder($routingKey . ' publish')
                    ->setParent($parentContext)
                    ->setSpanKind(SpanKind::KIND_PRODUCER)
                    // code
                    ->setAttribute(TraceAttributes::CODE_FUNCTION_NAME, sprintf('%s::%s', $class, $function))
                    ->setAttribute(TraceAttributes::CODE_FILE_PATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINE_NUMBER, $lineno)
                    // RabbitMQ
                    ->setAttribute(TraceAttributes::MESSAGING_RABBITMQ_DESTINATION_ROUTING_KEY, $routingKey)
                    // messaging
                    ->setAttribute(TraceAttributes::MESSAGING_SYSTEM, 'amqp')
                    ->setAttribute(TraceAttributes::MESSAGING_OPERATION_TYPE, 'publish')

                    ->setAttribute('messaging.destination', $routingKey)
                    ->setAttribute(TraceAttributes::MESSAGING_DESTINATION_NAME, $routingKey)
                    ->setAttribute('messaging.destination_publish.name', $routingKey)

                    ->setAttribute('messaging.destination.kind', 'queue')

                    ->setAttribute('messaging.rabbitmq.routing.key', $routingKey)
                    ->setAttribute('messaging.rabbitmq.destination.routing.key', $routingKey)

                    // network
                    ->setAttribute(TraceAttributes::NETWORK_PROTOCOL_NAME, 'amqp')
                    ->setAttribute(TraceAttributes::NETWORK_TRANSPORT, 'tcp');

                $span    = $spanBuilder->startSpan();
                $context = $span->storeInContext($parentContext);

                $headers    = $params[1] ?? [];
                $propagator = Globals::propagator();
                $propagator->inject($headers, ArrayAccessGetterSetter::getInstance(), $context);
                $params[1] = $headers;

                Context::storage()->attach($context);

                return $params;
            },
            post: static function (
                ChannelInterface $channel,
                array $params,
                bool|null $success,
                Throwable|null $exception,
            ): void {
                $scope = Context::storage()->scope();
                if (! $scope instanceof ContextStorageScopeInterface) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());

                if ($exception instanceof Throwable) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                }

                $span->end();
            },
        );
    }

    private static function consume(CachedInstrumentation $instrumentation): void
    {
        hook(
            ChannelInterface::class,
            'consume',
            pre: static function (ChannelInterface $channel, array $params, string $class, string $function, string|null $filename, int|null $lineno) use ($instrumentation): array {
                /** @var callable(BunnyMessage): mixed $callback */
                [$callback, $queue] = $params;
                assert(is_string($queue));

                /** @phpstan-ignore shipmonk.missingNativeReturnTypehint */
                $params[0] = static function (BunnyMessage $message) use ($callback, $queue, $instrumentation, $class, $function, $filename, $lineno) {
                    $parentContext = Globals::propagator()->extract($message->headers);

                    $spanBuilder = $instrumentation
                        ->tracer()
                        ->spanBuilder($queue . ' consumer')
                        ->setParent($parentContext)
                        ->setSpanKind(SpanKind::KIND_CONSUMER)
                        // code
                        ->setAttribute(TraceAttributes::CODE_FUNCTION_NAME, sprintf('%s::%s', $class, $function))
                        ->setAttribute(TraceAttributes::CODE_FILE_PATH, $filename)
                        ->setAttribute(TraceAttributes::CODE_LINE_NUMBER, $lineno)
                        // RabbitMQ
                        ->setAttribute(TraceAttributes::MESSAGING_RABBITMQ_DESTINATION_ROUTING_KEY, $message->routingKey)
                        ->setAttribute(TraceAttributes::MESSAGING_RABBITMQ_MESSAGE_DELIVERY_TAG, $message->deliveryTag)
                        // messaging
                        ->setAttribute(TraceAttributes::MESSAGING_SYSTEM, 'amqp')
                        ->setAttribute(TraceAttributes::MESSAGING_OPERATION_TYPE, 'receive')

                        ->setAttribute('messaging.destination', $queue)
                        ->setAttribute(TraceAttributes::MESSAGING_DESTINATION_NAME, $queue)
                        ->setAttribute('messaging.destination_publish.name', $queue)

                        ->setAttribute('messaging.destination.kind', 'queue')

                        ->setAttribute('messaging.rabbitmq.routing.key', $queue)
                        ->setAttribute('messaging.rabbitmq.destination.routing.key', $queue)

                        // network
                        ->setAttribute(TraceAttributes::NETWORK_PROTOCOL_NAME, 'amqp')
                        ->setAttribute(TraceAttributes::NETWORK_TRANSPORT, 'tcp');

                    $span    = $spanBuilder->startSpan();
                    $context = $span->storeInContext($parentContext);

                    Context::storage()->attach($context);

                    try {
                        return $callback($message);
                    } catch (Throwable $t) {
                        $span
                            ->setStatus(StatusCode::STATUS_ERROR, $t->getMessage())
                            ->setAttribute(TraceAttributes::ERROR_TYPE, $t::class);
                    } finally {
                        $span->end();
                    }
                };

                return $params;
            },
        );
    }

    private static function createInteractionWithQueueSpan(CachedInstrumentation $instrumentation, string $method): void
    {
        hook(
            ChannelInterface::class,
            $method,
            pre: static function (
                ChannelInterface $channel,
                array $params,
                string $class,
                string $function,
                string|null $filename,
                int|null $lineno,
            ) use (
                $instrumentation,
                $method,
            ): array {
                [$message] = $params;
                assert($message instanceof BunnyMessage);

                $parent = Context::getCurrent();

                $builder = $instrumentation
                    ->tracer()
                    ->spanBuilder($message->routingKey . ' ' . $method)
                    ->setParent($parent)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    // code
                    ->setAttribute(TraceAttributes::CODE_FUNCTION_NAME, sprintf('%s::%s', $class, $function))
                    ->setAttribute(TraceAttributes::CODE_FILE_PATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINE_NUMBER, $lineno)
                    // messaging
                    ->setAttribute(TraceAttributes::MESSAGING_SYSTEM, 'amqp')
                    ->setAttribute(TraceAttributes::MESSAGING_OPERATION_TYPE, $method)

                    ->setAttribute('messaging.destination.kind', 'queue')

                    ->setAttribute('messaging.rabbitmq.routing.key', $message->routingKey)
                    ->setAttribute('messaging.rabbitmq.destination.routing_key', $message->routingKey)
                    ->setAttribute('messaging.destination_publish.name', $message->routingKey)

                    ->setAttribute(TraceAttributes::MESSAGING_CLIENT_ID, $message->consumerTag)

                    ->setAttribute(TraceAttributes::NETWORK_PROTOCOL_NAME, 'amqp')
                    ->setAttribute(TraceAttributes::NETWORK_TRANSPORT, 'tcp');

                $span = $builder->startSpan();

                $context = $span->storeInContext($parent);

                Context::storage()->attach($context);

                return $params;
            },
            post: static function (
                ChannelInterface $channel,
                array $params,
                bool|null $success,
                Throwable|null $exception,
            ): void {
                $scope = Context::storage()->scope();
                if (! $scope instanceof ContextStorageScopeInterface) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());

                if ($exception instanceof Throwable) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                }

                $span->end();
            },
        );
    }
}
