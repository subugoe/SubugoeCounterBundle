<?php

declare(strict_types=1);

namespace Subugoe\CounterBundle\Command;

use Subugoe\CounterBundle\Controller\DefaultController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AdminReportCommand extends Command
{
    /**
     * @var DefaultController
     */
    private $defaultController;

    public function __construct(DefaultController $defaultController)
    {
        parent::__construct();

        $this->defaultController = $defaultController;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:generate:adminreport')
            ->setDescription('Generate and dispatch counter reports.')
            ->addArgument('month', InputArgument::OPTIONAL, 'The end month of the report.')
            ->addArgument('year', InputArgument::OPTIONAL, 'The year for which the report is requested.')
            ->addArgument('database', InputArgument::OPTIONAL, 'Should Database Report 1 be dispached?')
            ->addArgument('platform', InputArgument::OPTIONAL, 'Should Platform Report 1 be dispached?');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $month = (int) $input->getArgument('month');
        $year = (int) $input->getArgument('year');
        $counterBeginYear = 2017;

        if (isset($month) && !in_array($month, range(1, 12))) {
            $output->writeln('Error: Reporting end month should be between 1 and 12');
            exit;
        }

        if (!empty($year) && $counterBeginYear > $year || $year > date('Y')) {
            $output->writeln('Error: Reporting year should be between '.$counterBeginYear.' and '.date('Y'));
            exit;
        }

        $output->writeln('Start generating and dispatching reports.');
        $this->defaultController->reportGeneratingAndDispatchingToAdminAction($month, $year);
        $output->writeln('Reports are generated and dispatched.');
    }
}
