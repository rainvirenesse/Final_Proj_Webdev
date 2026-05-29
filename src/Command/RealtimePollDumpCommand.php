<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Admin\StaffNotificationPollService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:realtime:poll-dump', description: 'Dump /realtime/poll JSON for debugging')]
final class RealtimePollDumpCommand extends Command
{
    public function __construct(
        private readonly StaffNotificationPollService $pollService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->pollService->poll(new \DateTimeImmutable('-10 seconds'));
        $output->writeln((string) json_encode($data, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
