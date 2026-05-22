<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ensures client users have ROLE_CUSTOMER stored in the database for the mobile API.
 */
#[AsCommand(name: 'app:users:assign-customer-role', description: 'Add ROLE_CUSTOMER to non-staff users for customer API access')]
final class AssignCustomerRoleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $updated = 0;

        foreach ($this->em->getRepository(User::class)->findAll() as $user) {
            $roles = $this->getStoredRoles($user);
            if (\in_array('ROLE_STAFF', $roles, true) || \in_array('ROLE_ADMIN', $roles, true)) {
                continue;
            }

            if (!\in_array('ROLE_CUSTOMER', $roles, true)) {
                $roles[] = 'ROLE_CUSTOMER';
            }
            if (!\in_array('ROLE_USER', $roles, true)) {
                $roles[] = 'ROLE_USER';
            }
            $user->setRoles(array_values(array_unique($roles)));
            ++$updated;
        }

        $this->em->flush();
        $io->success(sprintf('Updated %d user(s) with ROLE_CUSTOMER.', $updated));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function getStoredRoles(User $user): array
    {
        $ref = new \ReflectionProperty(User::class, 'roles');
        $ref->setAccessible(true);
        /** @var list<string> $roles */
        $roles = $ref->getValue($user);

        return $roles;
    }
}
