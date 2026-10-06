<?php

declare(strict_types=1);

namespace Marko\Core\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryCompiler;
use Marko\Core\Discovery\DiscoverySkips;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Module\ModuleRepositoryInterface;
use Psr\Container\ContainerExceptionInterface;

/** @noinspection PhpUnused */
#[Command(name: 'discovery:cache', description: 'Compile the discovery cache')]
readonly class DiscoveryCacheCommand implements CommandInterface
{
    public function __construct(
        private DiscoveryCompiler $discoveryCompiler,
        private DiscoveryCache $discoveryCache,
        private ModuleRepositoryInterface $moduleRepository,
    ) {}

    /**
     * @throws ModuleException|ContainerExceptionInterface
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        try {
            $payload = $this->discoveryCompiler->compile($this->moduleRepository->all());
            $this->discoveryCache->write($payload);
        } catch (DiscoveryCacheException $e) {
            $output->writeLine($e->getMessage());
            $output->writeLine($e->getContext());
            $output->writeLine($e->getSuggestion());

            return 1;
        }

        $output->writeLine('Discovery cache compiled successfully.');
        $output->writeLine('Cache path: ' . $this->discoveryCache->path());
        $output->writeLine('preferences: ' . count($payload['preferences']));
        $output->writeLine('plugins: ' . count($payload['plugins']));
        $output->writeLine('observers: ' . count($payload['observers']));
        $output->writeLine('commands: ' . count($payload['commands']));
        $output->writeLine('modules: ' . count($payload['modules']));
        $output->writeLine('global middleware: ' . count($payload['globalMiddleware']));
        $output->writeLine('sections: ' . ($payload['sections'] === [] ? 'none' : implode(', ', array_map(
            fn (string $key, array $section): string => "$key (" . count($section) . ')',
            array_keys($payload['sections']),
            $payload['sections'],
        ))));

        // Files skipped because they reference an uninstalled Marko package are left out of the
        // cache; list them so a typo or missing dependency in a Preference or Plugin is visible.
        $skips = DiscoverySkips::all();
        $output->writeLine('skipped files: ' . count($skips));

        foreach ($skips as $skip) {
            $output->writeLine(
                "  $skip->filePath ($skip->className): missing $skip->missingClass ($skip->missingPackage)",
            );
        }

        return 0;
    }
}
