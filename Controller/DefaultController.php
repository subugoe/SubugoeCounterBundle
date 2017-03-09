<?php

namespace Subugoe\CounterBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    const DATABASE_REPORT_1 = 'DatabaseReport1';
    const PLATFORM_REPORT_1 = 'PlatformReport1';
    const REPORT_FORMAT = 'xls';

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
        $collections = $this->getParameter('counter_collections');
        $publisherArr = array_combine(array_column($collections, 'id'), array_column($collections, 'publisher'));
        $fulltitleArr = array_combine(array_column($collections, 'id'), array_column($collections, 'full_title'));
        $reportsDir = $this->getParameter('reports_dir');
        $platform = $this->getParameter('nlh_platform');
        $reportSubject = $this->getParameter('report_subject');
        $reportBody = $this->getParameter('report_body');
        $reportingStartSubject = $this->getParameter('reporting_start_subject');
        $reportingStartBody = $this->getParameter('reporting_start_body');
        $reportingEndSubject = $this->getParameter('reporting_end_subject');
        $reportingEndBody = $this->getParameter('reporting_end_body');
        $numberOfReportsSent = $this->getParameter('number_of_reports_sent');
        $fromAdmin = $this->getParameter('admin_nlh_email');
        $toAdmin = $this->getParameter('admin_nlh_email');
        $fs = $this->get('filesystem');
        if (!$fs->exists($reportsDir)) {
            $fs->mkdir($reportsDir);
        } else {
            $fs->remove($reportsDir);
            $fs->mkdir($reportsDir);
        }

        list($databaseReport1Data, $platformReport1data, $reportingPeriod) = $this->get('report_service')->reportService();

        // Informs NLH in charge of the start of reporting service
        $this->informAdminStart($reportingStartSubject, $fromAdmin, $toAdmin, $reportingStartBody);

        $i = 0;
        $customersInformed = '';

        // Generate and dispatch both Database Report 1 and Platform Report 1 in xls format
        if ((is_array($databaseReport1Data) && $databaseReport1Data !== []) &&
                (is_array($platformReport1data) && $platformReport1data !== []) &&
                (is_array($reportingPeriod) && $reportingPeriod !== [])) {
            foreach ($databaseReport1Data as $userIdentifier => $data) {
                $databaseReport1FileTarget = $this->generateDatabaseReport1($userIdentifier, $reportsDir, $data, $publisherArr, $fulltitleArr, $reportingPeriod, $platform);
                $platformReport1FileTarget = $this->generatePlatformReport1($userIdentifier, $reportsDir, key($data), $platformReport1data[$userIdentifier], $reportingPeriod, $platform);
                $repository = $this->getDoctrine()->getRepository('SubugoeCounterBundle:User');
                $toUser = $repository->findOneByIdentifier($userIdentifier)->getEmail();
                $this->dispatchReports($reportSubject, $fromAdmin, $toUser, $reportBody, $databaseReport1FileTarget, $platformReport1FileTarget);

                $customersInformed .= ++$i.'. '.key($data)."\r\n\r\n";
            }
        }

        // Informs NLH in charge of the end of reporting service
        $this->informAdminEnd($reportingEndSubject, $fromAdmin, $toAdmin, $reportingEndBody, $numberOfReportsSent, $customersInformed, $i);

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

    /*
     * Informs the Admin of the start of report service via e-mail
     *
     * @param string $reportingStartSubject The e-mail subject
     * @param string $fromAdmin The sender e-mail address
     * @param string $toAdmin The receiver e-mail address
     * @param string $reportingStartBody The e-mail body
     */
    protected function informAdminStart($reportingStartSubject, $fromAdmin, $toAdmin, $reportingStartBody)
    {
        $adminMessageStart = \Swift_Message::newInstance();
        $adminMessageStart->setSubject($reportingStartSubject);
        $adminMessageStart->setFrom($fromAdmin);
        $adminMessageStart->setTo($toAdmin);
        $adminMessageStart->setBody($reportingStartBody.' '.date('d.m-Y H:i:s'));
        $this->get('mailer')->send($adminMessageStart);
    }

    /*
     * Dispatches the generated reports via e-mail
     *
     * @param string $reportSubject The e-mail subject
     * @param string $fromAdmin The sender e-mail address
     * @param string $toAdmin The receiver e-mail address
     * @param string $reportBody The e-mail body
     * @param string $databaseReport1FileTarget The path to Database Report 1
     * @param string $platformReport1FileTarget The path to Platform Report 1
     */
    protected function dispatchReports($reportSubject, $fromAdmin, $toUser, $reportBody, $databaseReport1FileTarget, $platformReport1FileTarget)
    {
        $message = \Swift_Message::newInstance();
        $message->setSubject($reportSubject.' '.date('m-Y', strtotime('- 1 month')));
        $message->setFrom($fromAdmin);
        $message->setTo($toUser);
        $message->setBody($this->renderView('SubugoeCounterBundle:reports:emailbody.html.twig', ['reportBody' => $reportBody]), 'text/html');
        $message->attach(\Swift_Attachment::fromPath($databaseReport1FileTarget));
        $message->attach(\Swift_Attachment::fromPath($platformReport1FileTarget));
        $this->get('mailer')->send($message);
    }
    
    /*
     * Informs the Admin of the end of report service via e-mail
     *
     * @param string $reportingEndSubject The e-mail subject
     * @param string $fromAdmin The sender e-mail address
     * @param string $toAdmin The receiver e-mail address
     * @param string $reportingEndBody The e-mail body
     * @param string $numberOfReportsSent The e-mail body
     * @param string $customersInformed The e-mail body
     * @param integer $si The number of users informed
     *
     */
    protected function informAdminEnd($reportingEndSubject, $fromAdmin, $toAdmin, $reportingEndBody, $numberOfReportsSent, $customersInformed, $i)
    {
        $adminMessageEnd = \Swift_Message::newInstance();
        $adminMessageEnd->setSubject($reportingEndSubject);
        $adminMessageEnd->setFrom($fromAdmin);
        $adminMessageEnd->setTo($toAdmin);
        $adminMessageEnd->setBody($reportingEndBody.' '.date('d.m-Y H:i:s')."\r\n\r\n ".$numberOfReportsSent." ".$i."\r\n\r\n".$customersInformed);
        $this->get('mailer')->send($adminMessageEnd);
    }

    /*
     * Generates Database Report  1
     *
     * @param string $userIdentifier The user identifier
     * @param string $reportsDir The reports dir
     * @param array $data The user specific tracked data for Database Report 1
     * @param array $publisherArr The list of publishers
     * @param array $fulltitleArr The products full titles
     * @param array $reportingPeriod The reporting period
     * @param string $platform The name of hosting platform
     *
     * @return string $databaseReport1FileTarget The path to Database Report 1 file
     */
    protected function generateDatabaseReport1($userIdentifier, $reportsDir, $data, $publisherArr, $fulltitleArr, $reportingPeriod, $platform)
    {
        $fs = $this->get('filesystem');
        $databaseReport1FileName = self::DATABASE_REPORT_1.'_'.$userIdentifier.'.'.self::REPORT_FORMAT;
        $databaseReport1FileTarget = $reportsDir.$databaseReport1FileName;
        $fs->dumpFile(
                $databaseReport1FileTarget,
                $this->renderView(
                        'SubugoeCounterBundle:reports:databasereport1.xls.twig',
                        [
                                'databaseReport1' => $data,
                                'customer' => key($data),
                                'customerIdentifier' => $userIdentifier,
                                'publisherArr' => $publisherArr,
                                'fulltitleArr' => $fulltitleArr,
                                'reportingPeriod' => $reportingPeriod,
                                'platform' => $platform,
                        ]
                )
        );

        return $databaseReport1FileTarget;
    }

    /*
     * Generates Platform Report  1
     *
     * @param string $userIdentifier The user identifier
     * @param string $reportsDir The reports dir
     * @param string $user The user name
     * @param array $platformReport1data The user specific tracked data for Platform Report 1
     * @param array $reportingPeriod The reporting period
     * @param string $platform The name of hosting platform
     *
     * @return string $platformReport1FileTarget The path to Platform Report 1 file
     */
    protected function generatePlatformReport1($userIdentifier, $reportsDir, $user, $platformReport1data, $reportingPeriod, $platform)
    {
        $fs = $this->get('filesystem');
        $platformReport1FileName1 = self::PLATFORM_REPORT_1.'_'.$userIdentifier.'.'.self::REPORT_FORMAT;
        $platformReport1FileTarget = $reportsDir.$platformReport1FileName1;
        $fs->dumpFile(
                $platformReport1FileTarget,
                $this->renderView(
                        'SubugoeCounterBundle:reports:platformreport1.xls.twig',
                        [
                                'platformreport1' => $platformReport1data,
                                'customer' => $user,
                                'customerIdentifier' => $userIdentifier,
                                'reportingPeriod' => $reportingPeriod,
                                'platform' => $platform,
                        ]
                )
        );

        return $platformReport1FileTarget;
    }
}
