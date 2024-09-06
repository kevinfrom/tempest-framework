<?php

declare(strict_types=1);

namespace Tempest\Console;

use Tempest\Console\Actions\ExecuteConsoleCommand;
use Tempest\Console\Exceptions\ConsoleExceptionHandler;
use Tempest\Console\Input\ConsoleArgumentBag;
use Tempest\Container\Container;
use Tempest\Core\AppConfig;
use Tempest\Core\Application;
use Tempest\Core\Kernel;
use Tempest\Core\Tempest;
use Tempest\Log\Channels\AppendLogChannel;
use Tempest\Log\LogConfig;
use Tempest\Support\PathHelper;
use Throwable;

final readonly class ConsoleApplication implements Application
{
    public function __construct(
        private Container $container,
        private AppConfig $appConfig,
        private ConsoleArgumentBag $argumentBag,
    ) {}

    /** @param \Tempest\Core\DiscoveryLocation[] $discoveryLocations */
    public static function boot(
        string $name = 'Tempest',
        ?string $root = null,
        array $discoveryLocations = [],
    ): self
    {
        $container = Tempest::boot($root ?? getcwd(), $discoveryLocations);

        $application = $container->get(ConsoleApplication::class);

        // Application-specific config
        $consoleConfig = $container->get(ConsoleConfig::class);
        $consoleConfig->name = $name;

        $logConfig = $container->get(LogConfig::class);
        $logConfig->debugLogPath = PathHelper::make($container->get(Kernel::class)->root, '/log/debug.log');
        $logConfig->channels[] = new AppendLogChannel(PathHelper::make($container->get(Kernel::class)->root, '/log/tempest.log'));

        $container->get(AppConfig::class)->exceptionHandlers[] = $container->get(ConsoleExceptionHandler::class);

        return $application;
    }

    public function run(): void
    {
        try {
            $exitCode = ($this->container->get(ExecuteConsoleCommand::class))($this->argumentBag->getCommandName());

            exit($exitCode->value);
        } catch (Throwable $throwable) {
            foreach ($this->appConfig->exceptionHandlers as $exceptionHandler) {
                $exceptionHandler->handle($throwable);
            }

            throw $throwable;
        }
    }
}
