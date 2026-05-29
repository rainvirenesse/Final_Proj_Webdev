<?php

namespace App\Command;

use App\Service\Realtime\WebSocketBroadcastService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:realtime:ping',
    description: 'Send a test realtime event to staff dashboards',
)]
final class RealtimePingCommand extends Command
{
    public function __construct(
        private readonly WebSocketBroadcastService $realtime,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->realtime->publishToStaff('test.ping', [
            'message' => 'Hello from Symfony (app:realtime:ping)',
        ]);

        $io->success('Broadcasted test.ping to staff channel.');

        return Command::SUCCESS;
    }
}

