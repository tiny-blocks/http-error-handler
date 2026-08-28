<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Reporters;

use Sentry\ClientBuilder;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use TinyBlocks\Http\ErrorHandler\ErrorReporter;
use TinyBlocks\Http\ErrorHandler\Internal\Reporting\SentryScope;
use TinyBlocks\Http\ErrorHandler\ReportedError;
use TinyBlocks\Http\ErrorHandler\ReportingFilter;

/**
 * Reporter that captures a handled error onto the Sentry hub the application initialized.
 *
 * <p>Two ways in. {@see SentryReporter::initializedWith()} takes the three values every error
 * tracking provider is configured with, boots the SDK, and binds the resulting hub as the current
 * one, so an application registering it writes no SDK code at all. {@see SentryReporter::from()}
 * takes a hub the application already holds, and touches no global state.</p>
 *
 * <p>The SDK is not a dependency of this library, so an application registering this reporter
 * declares <code>sentry/sentry</code> itself.</p>
 */
final readonly class SentryReporter implements ErrorReporter
{
    private function __construct(private HubInterface $hub, private ReportingFilter $filter)
    {
    }

    /**
     * Creates a SentryReporter from an initialized hub.
     *
     * @param HubInterface $hub The hub the application initialized, owner of the DSN and the transport.
     * @param ReportingFilter $filter The rule deciding which handled errors become events.
     * @return SentryReporter The configured reporter.
     */
    public static function from(
        HubInterface $hub,
        ReportingFilter $filter = ReportingFilter::SERVER_ERRORS
    ): SentryReporter {
        return new SentryReporter(hub: $hub, filter: $filter);
    }

    /**
     * Builds a SentryReporter with the SDK booted from the given deployment values.
     *
     * <p>Boots a client and binds its hub as the current one, so whatever the SDK captures outside
     * this reporter, a fatal error most of all, reaches the same project. That bind is global
     * state, which is why it is stated in the name instead of hidden.</p>
     *
     * <p>An empty release becomes no release, because a working tree is not a version. An empty DSN
     * leaves the SDK disabled, which is what an environment with reporting switched off wants.</p>
     *
     * @param string $dsn The DSN of the Sentry project, empty to leave the SDK disabled.
     * @param string $release The version the deploy reports, as <code>package@version</code>.
     * @param string $environment The environment the deploy runs in.
     * @param ReportingFilter $filter The rule deciding which handled errors become events.
     * @return SentryReporter The configured reporter.
     */
    public static function initializedWith(
        string $dsn,
        string $release,
        string $environment,
        ReportingFilter $filter = ReportingFilter::SERVER_ERRORS
    ): SentryReporter {
        $client = ClientBuilder::create(options: [
            'dsn'         => $dsn,
            'release'     => $release === '' ? null : $release,
            'environment' => $environment
        ])->getClient();

        return new SentryReporter(hub: SentrySdk::setCurrentHub(hub: new Hub(client: $client)), filter: $filter);
    }

    public function report(ReportedError $error): void
    {
        if (!$this->filter->accepts(error: $error)) {
            return;
        }

        $this->hub->withScope(callback: function (Scope $scope) use ($error): void {
            SentryScope::apply(error: $error, scope: $scope);

            $this->hub->captureException(exception: $error->exception);
        });
    }
}
