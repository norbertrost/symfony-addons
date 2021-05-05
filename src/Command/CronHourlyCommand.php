<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vrok\SymfonyAddons\Event\CronHourlyEvent;

class CronHourlyCommand extends Command
{
    protected static $defaultName = 'cron:hourly';

    private LoggerInterface $logger;

    private EventDispatcherInterface $dispatcher;

    public function __construct(LoggerInterface $logger, EventDispatcherInterface $dispatcher)
    {
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Calls all event subscribers listening '
            .'to the CronHourlyEvent event. To be called via crontab automatically.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Running CronHourlyEvent');
        $this->dispatcher->dispatch(new CronHourlyEvent());

        return 0;
    }
}
