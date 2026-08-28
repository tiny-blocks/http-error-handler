<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit\Reporters;

use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sentry\SentrySdk;
use Sentry\Severity;
use Test\TinyBlocks\Http\ErrorHandler\Unit\ContextualException;
use Test\TinyBlocks\Http\ErrorHandler\Unit\PrioritizedException;
use Test\TinyBlocks\Http\ErrorHandler\Unit\RecordingSentry;
use Test\TinyBlocks\Http\ErrorHandler\Unit\RoutingHandler;
use Test\TinyBlocks\Http\ErrorHandler\Unit\TaggingHandler;
use Test\TinyBlocks\Http\ErrorHandler\Unit\UnavailableExceptions;
use Test\TinyBlocks\Http\ErrorHandler\Unit\UnmappedExceptions;
use Test\TinyBlocks\Http\ErrorHandler\Unit\UnprocessableExceptions;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\CorrelationId\CorrelationId;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\MappedError;
use TinyBlocks\Http\ErrorHandler\Reporters\SentryReporter;
use TinyBlocks\Http\ErrorHandler\ReportingFilter;
use TinyBlocks\Http\ErrorHandler\ReportingPriority;
use TinyBlocks\Http\ErrorHandler\RouteMiddleware;

final class SentryReporterTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        SentryReporter::initializedWith(
            dsn: 'https://examplePublicKey@o0.ingest.sentry.io/1',
            release: '',
            environment: 'local'
        );
    }

    public function testReportWhenServerErrorThenEventReachesSentry(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws an exception no rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the event carries the exception the middleware handled */
        self::assertNotNull($sentry->sent);
        self::assertSame('Boom', $sentry->sent->getExceptions()[0]->getValue());
        self::assertSame(Exception::class, $sentry->sent->getExceptions()[0]->getType());
    }

    public function testReportWhenClientErrorThenNothingReachesSentry(): void
    {
        /** @Given a request */
        $request = new ServerRequest('PUT', '/v1/orders/1');

        /** @And a handler that throws an exception a rule maps to 422 */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new LogicException('Invalid payload'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub under the default filter */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnprocessableExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the quota is spared, because a 4xx is the described outcome of a client mistake */
        self::assertNull($sentry->sent);
    }

    public function testInitializedWithWhenDsnIsEmptyThenTheSdkStaysDisabled(): void
    {
        /** @Given an environment with reporting switched off */
        $dsn = '';

        /** @When a reporter is built from it */
        SentryReporter::initializedWith(dsn: $dsn, release: 'client-gateway@1.0.0', environment: 'local');

        /** @Then the SDK holds no DSN, so nothing is ever sent */
        self::assertNull(SentrySdk::getCurrentHub()->getClient()?->getOptions()->getDsn());
    }

    public function testReportWhenServerErrorThenContextCarriesMethodAndPath(): void
    {
        /** @Given a request carrying a method and a path */
        $request = new ServerRequest('POST', '/v1/orders');

        /** @And a handler that throws an exception no rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the path travels as context, never as a tag, because a tag is an index */
        self::assertNotNull($sentry->sent);
        self::assertSame('POST', $sentry->sent->getContexts()['http']['method']);
        self::assertSame('/v1/orders', $sentry->sent->getContexts()['http']['path']);
        self::assertArrayNotHasKey('path', $sentry->sent->getTags());
    }

    public function testReportWhenMappedServerErrorThenTagsCarryTheMappedCode(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws an exception a rule maps to 503 */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Upstream down'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnavailableExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the tags carry the rule that matched, which groups the issue by domain outcome */
        self::assertNotNull($sentry->sent);
        self::assertSame('503', $sentry->sent->getTags()['status_code']);
        self::assertSame('true', $sentry->sent->getTags()['error_mapped']);
        self::assertSame('UPSTREAM_UNAVAILABLE', $sentry->sent->getTags()['error_code']);
    }

    public function testReportWhenRequestCarriesCorrelationIdThenEventIsTagged(): void
    {
        /** @Given a correlation identifier the request carries */
        $correlationId = new readonly class implements CorrelationId {
            public function toString(): string
            {
                return '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';
            }
        };

        /** @And a request carrying it */
        $request = new ServerRequest('GET', '/v1/orders')
            ->withAttribute(CorrelationIdMiddleware::ATTRIBUTE_NAME, $correlationId);

        /** @And a handler that throws an exception no rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the issue and the log entries of every service share one identifier */
        self::assertNotNull($sentry->sent);
        self::assertSame('0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b', $sentry->sent->getTags()['correlation_id']);
    }

    public function testReportWhenRequestCarriesNoCorrelationIdThenTagIsAbsent(): void
    {
        /** @Given a request with no correlation identifier */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws an exception no rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the tag is absent instead of invented */
        self::assertNotNull($sentry->sent);
        self::assertArrayNotHasKey('correlation_id', $sentry->sent->getTags());
    }

    public function testReportWhenUnmappedFilterThenMappedClientErrorIsDropped(): void
    {
        /** @Given a request */
        $request = new ServerRequest('PUT', '/v1/orders/1');

        /** @And a handler that throws an exception a rule maps to 422 */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new LogicException('Invalid payload'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware whose filter adds back what no rule described */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnprocessableExceptions())
            ->withReporter(
                reporter: SentryReporter::from(
                    hub: $sentry->hub(),
                    filter: ReportingFilter::SERVER_ERRORS_AND_UNMAPPED
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then a described client error stays out, because validation is expected behavior */
        self::assertNull($sentry->sent);
    }

    public function testReportWhenUnmappedServerErrorThenTagsDescribeTheStatus(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws an exception no rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the tags state the status and that no rule described the exception */
        self::assertNotNull($sentry->sent);
        self::assertSame('500', $sentry->sent->getTags()['status_code']);
        self::assertSame('false', $sentry->sent->getTags()['error_mapped']);
        self::assertArrayNotHasKey('error_code', $sentry->sent->getTags());
    }

    public function testInitializedWithWhenReleaseIsEmptyThenNoReleaseIsReported(): void
    {
        /** @Given a deployment that carries no version, as a working tree does not */
        $release = '';

        /** @When a reporter is built from it */
        SentryReporter::initializedWith(
            dsn: 'https://examplePublicKey@o0.ingest.sentry.io/1',
            release: $release,
            environment: 'local'
        );

        /** @Then the release is absent instead of an empty one */
        self::assertNull(SentrySdk::getCurrentHub()->getClient()?->getOptions()->getRelease());
    }

    public function testReportWhenALayerBelowTagsTheRequestThenTheEventCarriesIt(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a layer that learns the tenant while handling it, then fails */
        $handler = new TaggingHandler(
            key: 'organization_id',
            value: '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b',
            failure: new RuntimeException('Upstream down')
        );

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnavailableExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then what the request accumulated below survives the immutable copies above it */
        self::assertNotNull($sentry->sent);
        self::assertSame('0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b', $sentry->sent->getTags()['organization_id']);
    }

    public function testReportWhenContextDeclaresNoPriorityThenTheStatusDecidesIt(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/unknown');

        /** @And a handler throwing a 4xx no rule describes, whose context declares no priority */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(
            new ContextualException(tags: ['service' => 'identity'], status: Code::NOT_FOUND->value)
        );

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware whose filter adds back what no rule described */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(
                reporter: SentryReporter::from(
                    hub: $sentry->hub(),
                    filter: ReportingFilter::SERVER_ERRORS_AND_UNMAPPED
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the client error falls back to the middle of the scale, and the tags still travel */
        self::assertNotNull($sentry->sent);
        self::assertSame('identity', $sentry->sent->getTags()['service']);
        self::assertEquals(Severity::warning(), $sentry->sent->getLevel());
    }

    public function testReportWhenExceptionCarriesContextThenItsTagsReachTheEvent(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler throwing an exception that names the service that refused */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new ContextualException(tags: ['service' => 'payment']));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnavailableExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then what only the raising site knew becomes an index at the provider */
        self::assertNotNull($sentry->sent);
        self::assertSame('payment', $sentry->sent->getTags()['service']);
        self::assertEquals(Severity::fatal(), $sentry->sent->getLevel());
    }

    public function testReportWhenUndescribedServerErrorThenEventIsLeveledAsFatal(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws an exception no rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then a condition nobody described reaches the provider at the top of the scale */
        self::assertNotNull($sentry->sent);
        self::assertEquals(Severity::fatal(), $sentry->sent->getLevel());
    }

    public function testInitializedWithThenTheCurrentHubCarriesTheDeploymentValues(): void
    {
        /** @Given the deployment values an application resolves from its environment */
        $dsn = 'https://examplePublicKey@o0.ingest.sentry.io/1';

        /** @When a reporter is built from them */
        SentryReporter::initializedWith(dsn: $dsn, release: 'client-gateway@1.0.0', environment: 'development');

        /** @Then the SDK is booted and its hub is the current one, carrying those values */
        $options = SentrySdk::getCurrentHub()->getClient()?->getOptions();
        self::assertNotNull($options);
        self::assertSame('client-gateway@1.0.0', $options->getRelease());
        self::assertSame('development', $options->getEnvironment());
        self::assertSame('1', $options->getDsn()?->getProjectId());
    }

    public function testReportWhenTheConsumerReplacesTheRuleThenItsPriorityDecides(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler throwing a server error the library would call high */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Upstream down'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware whose consumer decided a 5xx deserves no interruption */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnavailableExceptions())
            ->withPriority(
                resolver: static fn(?MappedError $mapped, Code $status): ReportingPriority => ReportingPriority::LOW
            )
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the rule the consumer registered replaces the one the library ships */
        self::assertNotNull($sentry->sent);
        self::assertEquals(Severity::info(), $sentry->sent->getLevel());
    }

    public function testReportWhenUnmappedFilterThenMappedServerErrorReachesSentry(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws an exception a rule maps to 503 */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Upstream down'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware whose filter adds back what no rule described */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnavailableExceptions())
            ->withReporter(
                reporter: SentryReporter::from(
                    hub: $sentry->hub(),
                    filter: ReportingFilter::SERVER_ERRORS_AND_UNMAPPED
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then a described server failure still reaches the provider */
        self::assertNotNull($sentry->sent);
        self::assertSame('503', $sentry->sent->getTags()['status_code']);
    }

    public function testReportWhenClientErrorIsForwardedThenEventIsLeveledAsWarning(): void
    {
        /** @Given a request */
        $request = new ServerRequest('PUT', '/v1/orders/1');

        /** @And a handler that throws an exception a rule maps to 422 */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new LogicException('Invalid payload'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware whose filter keeps every handled error */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnprocessableExceptions())
            ->withReporter(
                reporter: SentryReporter::from(
                    hub: $sentry->hub(),
                    filter: ReportingFilter::EVERY_ERROR
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then a client error is a warning, the same split the log entry uses */
        self::assertNotNull($sentry->sent);
        self::assertEquals(Severity::warning(), $sentry->sent->getLevel());
    }

    public function testReportWhenTheRouteIsNamedThenTheEventCarriesItAsTransaction(): void
    {
        /** @Given a request whose path carries an identifier */
        $request = new ServerRequest('POST', '/v1/orders/1');

        /** @And a handler that throws an exception no rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request through a stack that names the route */
        $middleware->process($request, new RoutingHandler(
            handler: $handler,
            middleware: RouteMiddleware::resolvedBy(
                resolver: static fn(ServerRequestInterface $request): string => 'POST /v1/orders/{id}'
            )
        ));

        /** @Then the transaction is the pattern, which is what keeps the dimension low cardinality */
        self::assertNotNull($sentry->sent);
        self::assertSame('POST /v1/orders/{id}', $sentry->sent->getTransaction());
    }

    public function testReportWhenContextDeclaresACriticalFailureThenSeverityIsFatal(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler throwing a failure the raising site considers the worst it can name */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(
            new PrioritizedException(priority: ReportingPriority::CRITICAL)
        );

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnavailableExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the top of the neutral scale reaches the provider as the top of its own */
        self::assertNotNull($sentry->sent);
        self::assertEquals(Severity::fatal(), $sentry->sent->getLevel());
    }

    public function testReportWhenUnmappedFilterThenUnmappedClientErrorReachesSentry(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/unknown');

        /** @And a handler that throws an exception declaring a 4xx that no rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom', Code::NOT_FOUND->value));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware whose filter adds back what no rule described */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(
                reporter: SentryReporter::from(
                    hub: $sentry->hub(),
                    filter: ReportingFilter::SERVER_ERRORS_AND_UNMAPPED
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the gap in the mapping table surfaces even though the status is a 4xx */
        self::assertNotNull($sentry->sent);
        self::assertSame('404', $sentry->sent->getTags()['status_code']);
    }

    public function testReportWhenExceptionCarriesNoContextThenNothingIsAddedToTheEvent(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler throwing an exception that carries no supplement */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Upstream down'));

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnavailableExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the event carries the request tags alone, the behavior every exception had before */
        self::assertNotNull($sentry->sent);
        self::assertArrayNotHasKey('service', $sentry->sent->getTags());
        self::assertEquals(Severity::error(), $sentry->sent->getLevel());
    }

    public function testReportWhenContextOverridesPriorityThenSeverityFollowsTheOverride(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler throwing a server error the raising site considers unremarkable */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(
            new PrioritizedException(priority: ReportingPriority::LOW)
        );

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnavailableExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the declared priority wins over the one a 503 would have yielded */
        self::assertNotNull($sentry->sent);
        self::assertEquals(Severity::info(), $sentry->sent->getLevel());
    }

    public function testReportWhenBothTheRequestAndTheExceptionTagTheSameNameThenTheExceptionWins(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a layer tagging a name the exception also carries */
        $handler = new TaggingHandler(
            key: 'service',
            value: 'organization',
            failure: new ContextualException(tags: ['service' => 'payment'])
        );

        /** @And a Sentry transport recording whatever the hub sends */
        $sentry = new RecordingSentry();

        /** @And a middleware reporting to that hub */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnavailableExceptions())
            ->withReporter(reporter: SentryReporter::from(hub: $sentry->hub()))
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the one that describes this failure beats the one that describes the request */
        self::assertNotNull($sentry->sent);
        self::assertSame('payment', $sentry->sent->getTags()['service']);
    }
}
