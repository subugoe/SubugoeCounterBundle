<?php

namespace Subugoe\CounterBundle\Controller;

use Subugoe\CounterBundle\Service\MailService;
use Subugoe\CounterBundle\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;

class DefaultController extends AbstractController
{
    /**
     * @var string The label for federated/automated Searches as per COUNTER definitions
     */
    const FEDERATED_AUTOMATED_SEARCHES = 'Searches-federated and automated';

    /**
     * @var string The label for Record Views Searches as per COUNTER definitions
     */
    const RECORD_VIEWS = 'Record Views';

    /**
     * @var string The label for Regular Searches as per COUNTER definitions
     */
    const REGULAR_SEARCHES = 'Regular Searches';

    /**
     * @var string The label for Result Clicks Searches as per COUNTER definitions
     */
    const RESULT_CLICKS = 'Result Clicks';

    /*
     * @var array An array containing matches to tracking abbreviation in Piwik database
     */
    protected $counterTerms = [
        'RS' => [0, self::REGULAR_SEARCHES, 'Searches'],
        'SFA' => [1, self::FEDERATED_AUTOMATED_SEARCHES, 'Searches'],
        'RV' => [2, self::RECORD_VIEWS, 'Requests'],
        'RC' => [3, self::RESULT_CLICKS, 'Requests'],
    ];

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var ReportService
     */
    private $reportService;

    public function __construct(ReportService $reportService, MailService $mailService)
    {
        $this->reportService = $reportService;
        $this->mailService = $mailService;
    }

    /**
     * Generates and dispatch Database Report 1 and/or Platform Report 1 to NLH admin.
     */
    public function reportGeneratingAndDispatchingToAdminAction(int $month, int $year): Response
    {
        $reportsDir = $this->getParameter('reports_dir');
        $this->checkIfReportDirExists($reportsDir);

        [$databaseReport1Data, $platformReport1data, $reportingPeriod, $coveredPeriodStart, $coveredPeriodEnd] = $this->reportService->reportService($month, $year);

        // Generate and dispatch both Database Report 1 and Platform Report 1 in xls format
        if ((is_array($databaseReport1Data) && $databaseReport1Data !== []) &&
            (is_array($platformReport1data) && $platformReport1data !== []) &&
            (is_array($reportingPeriod) && $reportingPeriod !== [])) {
            foreach ($databaseReport1Data as $userIdentifier => $data) {
                $databaseReport1FileTarget = $this->reportService->generateDatabaseReport1($userIdentifier, $reportsDir, $data, $reportingPeriod, $coveredPeriodStart, $coveredPeriodEnd);
                $platformReport1FileTarget = $this->reportService->generatePlatformReport1($userIdentifier, $reportsDir, key($data), $platformReport1data[$userIdentifier], $reportingPeriod, $coveredPeriodStart, $coveredPeriodEnd);
                $repository = $this->getDoctrine()->getRepository('SubugoeCounterBundle:User');
                $institiution = $repository->findOneByIdentifier($userIdentifier)->getInstitution();
                $this->mailService->dispatchAdminReports($this->getParameter('admin_email'), $platformReport1FileTarget, $institiution);
            }
        }

        $response = new Response();
        $response->setContent('Reports are generated and dispatched.');
        $response->headers->set('Content-Type', 'text/plain');
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }

    /**
     * Generates and dispatch cumulative Database Report 1.
     */
    public function cumulativeDatabaseReportAction(int $month, int $year): Response
    {
        $reportsDir = $this->getParameter('reports_dir');
        $this->checkIfReportDirExists($reportsDir);
        [$databaseReport1Data, $platformReport1data, $reportingPeriod, $coveredPeriodStart, $coveredPeriodEnd] = $this->reportService->reportService($month,
            $year);
        $databaseReport1FileTarget = $this->reportService->generateCumulativeDatabaseReport1($reportsDir,
            $databaseReport1Data, $reportingPeriod, $coveredPeriodStart, $coveredPeriodEnd);
        $this->mailService->dispatchCumulativeDatabaseReport($databaseReport1FileTarget, $year);

        $response = new Response();
        $response->setContent('Report is generated and dispatched.');
        $response->headers->set('Content-Type', 'text/plain');
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }

    /**
     * Generates and dispatch Database Report 1 and/or Platform Report 1.
     */
    public function reportGeneratingAndDispatchingAction(int $month, int $year, int $database, int $platform): Response
    {
        $reportsDir = $this->getParameter('reports_dir');
        $this->checkIfReportDirExists($reportsDir);

        [$databaseReport1Data, $platformReport1data, $reportingPeriod, $coveredPeriodStart, $coveredPeriodEnd] = $this->reportService->reportService($month,
            $year);

        // Informs NLH in charge of the start of reporting service
        $this->mailService->informAdminStart();

        $i = 0;
        $customersInformed = '';

        // Generate and dispatch both Database Report 1 and Platform Report 1 in xls format
        if ((is_array($databaseReport1Data) && $databaseReport1Data !== []) &&
            (is_array($platformReport1data) && $platformReport1data !== []) &&
            (is_array($reportingPeriod) && $reportingPeriod !== [])) {
            foreach ($databaseReport1Data as $userIdentifier => $data) {
                    $databaseReport1FileTarget = $this->reportService->generateDatabaseReport1($userIdentifier, $reportsDir,
                        $data, $reportingPeriod, $coveredPeriodStart, $coveredPeriodEnd);
                    $platformReport1FileTarget = $this->reportService->generatePlatformReport1($userIdentifier, $reportsDir,
                        key($data), $platformReport1data[$userIdentifier], $reportingPeriod, $coveredPeriodStart,
                        $coveredPeriodEnd);
                    $repository = $this->getDoctrine()->getRepository('SubugoeCounterBundle:User');
                    $toUser = $repository->findOneByIdentifier($userIdentifier)->getEmail();
                    $this->mailService->dispatchReports($toUser, $databaseReport1FileTarget, $platformReport1FileTarget,
                        $database, $platform);

                    $customersInformed .= ++$i . '. ' . key($data) . "\r\n\r\n";
            }
        }

        // Informs NLH in charge of the end of reporting service
        $this->mailService->informAdminEnd($customersInformed, $i);

        $response = new Response();
        $response->setContent('Reports are generated and dispatched.');
        $response->headers->set('Content-Type', 'text/plain');
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }

    /**
     * Checks if report directory exists
     * If not it does create it.
     */
    private function checkIfReportDirExists(string $reportsDir): void
    {
        $fs = new Filesystem();
        if (!$fs->exists($reportsDir)) {
            $fs->mkdir($reportsDir);
        } else {
            $fs->remove($reportsDir);
            $fs->mkdir($reportsDir);
        }
    }
}
