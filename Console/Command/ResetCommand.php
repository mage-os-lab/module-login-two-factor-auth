<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Console\Command;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\LoginTwoFactorAuth\Model\Storage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ResetCommand extends Command
{
    public function __construct(
        private readonly Storage $storage,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mageos:customer-2fa:reset')
            ->setDescription('Turn off two-factor authentication for a storefront customer')
            ->addArgument('email', InputArgument::REQUIRED, 'Customer email')
            ->addOption('website', 'w', InputOption::VALUE_REQUIRED, 'Website ID (when accounts are per website)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, fn (): int => $this->reset($input, $output));
    }

    private function reset(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $websiteId = $input->getOption('website');
        try {
            $customer = $this->customerRepository->get($email, $websiteId !== null ? (int) $websiteId : null);
        } catch (NoSuchEntityException) {
            $output->writeln(sprintf('<error>Customer %s not found.</error>', $email));
            return Command::FAILURE;
        }

        if ($this->storage->delete((int) $customer->getId())) {
            $output->writeln(sprintf('<info>Two-factor authentication turned off for %s.</info>', $email));
        } else {
            $output->writeln(sprintf('Two-factor authentication was not active for %s.', $email));
        }

        return Command::SUCCESS;
    }
}
