<?php

namespace Subugoe\CounterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /*
     * Homepage of COUNTER Reports
     */
    public function indexAction()
    {
        return $this->render('SubugoeCounterBundle:Default:index.html.twig');
    }

    /*
     * Returns Platform Report 1 in json format
     *
     * @param string $identifier The user identifier
     * @param string $period The report period
     *
     * @return array $platformReport1 The Platform Report 1
     */
    public function platformReport1Action(string $identifier, string $period): Response
    {
        $platformReport1 = $this->get('report_service')->getPlatformReport1Data($identifier, $period);

        return $this->json($platformReport1);
    }

    /*
     * Returns Database Report 1 in json format
     *
     * @param string $identifier The user identifier
     * @param string $period The report period
     *
     * @return array $databaseReport1 The Database Report 1
     */
    public function databaseReport1Action(string $identifier, string $period): Response
    {
        $databaseReport1 = $this->get('report_service')->getDatabaseReport1Data($identifier, $period);

        return $this->json($databaseReport1);
    }

    /*
     * Generates and dispatch Database Report 1 and Platform Report 1
     */
    public function reportAction(): Response
    {
        $reportsDir = $this->getParameter('reports_dir');
        $fs = $this->get('filesystem');
        if (!$fs->exists($reportsDir)) {
            $fs->mkdir($reportsDir);
        } else {
            $fs->remove($reportsDir);
            $fs->mkdir($reportsDir);
        }

        $reportService = $this->get('report_service');

        list($databaseReport1Data, $platformReport1data, $reportingPeriod) = $reportService->reportService();

        // Informs NLH in charge of the start of reporting service
        $reportService->informAdminStart();

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
                $reportService->dispatchReports($toUser, $databaseReport1FileTarget, $platformReport1FileTarget);

                $customersInformed .= ++$i.'. '.key($data)."\r\n\r\n";
            }
        }

        // Informs NLH in charge of the end of reporting service
        $reportService->informAdminEnd($customersInformed, $i);

        $transport = $this->get('mailer')->getTransport();
        $spool = $transport->getSpool();
        $spool->flushQueue($this->container->get('swiftmailer.transport.real'));

        $fs->remove($reportsDir);

        $response = new Response();
        $response->setContent('Reports are generated and dispatched.');
        $response->headers->set('Content-Type', 'text/plain');
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }
}
