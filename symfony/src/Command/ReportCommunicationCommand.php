<?php

namespace App\Command;

use App\Manager\ReportManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReportCommunicationCommand extends Command
{
    /**
     * @var ReportManager
     */
    private $reportManager;

    public function __construct(ReportManager $reportManager)
    {
        parent::__construct();

        $this->reportManager = $reportManager;
    }

    protected function configure()
    {
        $this
            ->setName('report:communication')
            ->setDescription('Build outdated or missing communication reports');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->reportManager->createReports($output);

        return 0;
    }

}