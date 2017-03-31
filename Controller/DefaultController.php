<?php

namespace Subugoe\CounterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /*
     * @var string The label for Regular Searches as per COUNTER definitions
     */
    const REGULAR_SEARCHES = 'Regular Searches';

    /*
     * @var string The label for federated/automated Searches as per COUNTER definitions
     */
    const FEDERATED_AUTOMATED_SEARCHES = 'Searches-federated and automated';

    /*
     * @var string The label for Record Views Searches as per COUNTER definitions
     */
    const RECORD_VIEWS = 'Record Views';

    /*
     * @var string The label for Result Clicks Searches as per COUNTER definitions
     */
    const RESULT_CLICKS = 'Result Clicks';

    /*
     * @var array An array containing matches to tracking abbreviation in Piwik database
     */
    protected $counterTerms = ['RS' => [0, self::REGULAR_SEARCHES, 'Searches'], 'SFA' => [1, self::FEDERATED_AUTOMATED_SEARCHES, 'Searches'], 'RV' => [2, self::RECORD_VIEWS, 'Requests'], 'RC' => [3, self::RESULT_CLICKS, 'Requests']];

    /*
     * Generates and dispatch Database Report 1 and Platform Report 1
     */
    public function reportGeneratingAndDispatchingAction(): Response
    {
        $reportsDir = $this->getParameter('reports_dir');
        $fs = $this->get('filesystem');
        if (!$fs->exists($reportsDir)) {
            $fs->mkdir($reportsDir);
        } else {
            $fs->remove($reportsDir);
            $fs->mkdir($reportsDir);
        }

        $reportService = $this->get('subugoe_counter.report_service');
        $mailService = $this->get('subugoe_counter.mail_service');

        list($databaseReport1Data, $platformReport1data, $reportingPeriod) = $reportService->reportService();

        // Informs NLH in charge of the start of reporting service
        $mailService->informAdminStart();

        $i = 0;
        $customersInformed = '';

        // Generate and dispatch both Database Report 1 and Platform Report 1 in xls format
        if ((is_array($databaseReport1Data) && $databaseReport1Data !== []) &&
                (is_array($platformReport1data) && $platformReport1data !== []) &&
                (is_array($reportingPeriod) && $reportingPeriod !== [])) {
            foreach ($databaseReport1Data as $userIdentifier => $data) {
                $databaseReport1FileTarget = $reportService->generateDatabaseReport1($userIdentifier, $reportsDir, $data, $reportingPeriod);
                $platformReport1FileTarget = $reportService->generatePlatformReport1($userIdentifier, $reportsDir, key($data), $platformReport1data[$userIdentifier], $reportingPeriod);
                $repository = $this->getDoctrine()->getRepository('SubugoeCounterBundle:User');
                $toUser = $repository->findOneByIdentifier($userIdentifier)->getEmail();
                $mailService->dispatchReports($toUser, $databaseReport1FileTarget, $platformReport1FileTarget);

                $customersInformed .= ++$i.'. '.key($data)."\r\n\r\n";
            }
        }

        // Informs NLH in charge of the end of reporting service
        $mailService->informAdminEnd($customersInformed, $i);

        $response = new Response();
        $response->setContent('Reports are generated and dispatched.');
        $response->headers->set('Content-Type', 'text/plain');
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }
}
