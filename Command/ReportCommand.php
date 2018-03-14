<?php

namespace Subugoe\CounterBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Subugoe\CounterBundle\Controller\DefaultController;
use Symfony\Component\Console\Input\InputArgument;

class ReportCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:generate:report')
            ->setDescription('Generate and dispatch counter reports.')

            ->addArgument('month', InputArgument::OPTIONAL, 'The end month of the report.')
            ->addArgument('year', InputArgument::OPTIONAL, 'The year for which the report is requested.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $month = $input->getArgument('month');
        $year = $input->getArgument('year');
        $counterBeginYear = 2017;

        if (isset($month) && !in_array($month, range(1, 12))) {
            $output->writeln('Error: Reporting end month should be between 1 and 12');
            exit;
        }

        if (!empty($year) && $counterBeginYear > $year || $year > date("Y")) {
            $output->writeln('Error: Reporting year should be between '.$counterBeginYear.' and '.date("Y"));
            exit;
        }

        $output->writeln('Start generating and dispatching reports.');
        $report = new DefaultController();
        $report->setContainer($this->getContainer());
        $report->reportGeneratingAndDispatchingAction($month, $year);
        $output->writeln('Reports are generated and dispatched.');
    }
}
