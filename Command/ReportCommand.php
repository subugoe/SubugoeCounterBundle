<?php

namespace Subugoe\CounterBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Subugoe\CounterBundle\Controller\DefaultController;

class ReportCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:generate:report')
            ->setDescription('Generate and dispatch counter reports.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Start generating and dispatching reports.');
        $report = new DefaultController();
        $report->setContainer($this->getContainer());
        $report->reportGeneratingAndDispatchingAction();
        $output->writeln('Reports are generated and dispatched.');
    }
}
