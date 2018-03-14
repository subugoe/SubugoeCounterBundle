<?php

namespace Subugoe\CounterBundle\Service;

use Symfony\Bundle\TwigBundle\TwigEngine;
use Swift_Mailer;

/**
 * Service for sending generated counter reports.
 */
class MailService
{
    /**
     * @var Swift_Mailer
     */
    protected $mailer;

    /**
     * @var TwigEngine
     */
    protected $templating;

    /*
     * @var string Admin E-mail
     */
    private $adminEmail;

    /*
     * @var Report E-Mail subject
     */
    private $reportSubject;

    /*
     * @var string Report E-Mail body
     */
    private $reportBody;

    /*
     * @var string Reporting service start E-mail subject
     */
    private $reportingStartSubject;

    /*
     * @var string Reporting service start E-mail body
     */
    private $reportingStartBody;

    /*
     * @var string Reporting service end E-mail subject
     */
    private $reportingEndSubject;

    /*
     * @var string Reporting service end E-mail body
     */
    private $reportingEndBody;

    /*
     * @var string Number of reports sent
     */
    private $numberOfReportsSent;

    /**
     * MailService constructor.
     *
     * @param Swift_Mailer $mailer
     * @param TwigEngine   $templating
     */
    public function __construct(Swift_Mailer $mailer, TwigEngine $templating)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
    }

    public function setConfig($adminEmail, $reportSubject, $reportBody, $reportingStartSubject, $reportingStartBody, $reportingEndSubject, $reportingEndBody, $numberOfReportsSent)
    {
        $this->adminEmail = $adminEmail;
        $this->reportSubject = $reportSubject;
        $this->reportBody = $reportBody;
        $this->reportingStartSubject = $reportingStartSubject;
        $this->reportingStartBody = $reportingStartBody;
        $this->reportingEndSubject = $reportingEndSubject;
        $this->reportingEndBody = $reportingEndBody;
        $this->numberOfReportsSent = $numberOfReportsSent;
    }

    /*
     * Informs the Admin of the start of report service via e-mail
     */
    public function informAdminStart()
    {
        $adminMessageStart = \Swift_Message::newInstance();
        $adminMessageStart->setSubject($this->reportingStartSubject);
        $adminMessageStart->setFrom($this->adminEmail);
        $adminMessageStart->setTo($this->adminEmail);
        $adminMessageStart->setBody($this->reportingStartBody.' '.date('d.m-Y H:i:s'));
        $this->mailer->send($adminMessageStart);
    }

    /*
     * Informs the Admin of the end of report service via e-mail
     *
     * @param string $customersInformed The e-mail body
     * @param integer $si The number of users informed
     *
     */
    public function informAdminEnd($customersInformed, $i)
    {
        $adminMessageEnd = \Swift_Message::newInstance();
        $adminMessageEnd->setSubject($this->reportingEndSubject);
        $adminMessageEnd->setFrom($this->adminEmail);
        $adminMessageEnd->setTo($this->adminEmail);
        $adminMessageEnd->setBody($this->reportingEndBody.' '.date('d.m-Y H:i:s')."\r\n\r\n ".$this->numberOfReportsSent.' '.$i."\r\n\r\n".$customersInformed);
        $this->mailer->send($adminMessageEnd);
    }

    /*
     * Dispatches the generated reports via e-mail
     *
     * @param string $toAdmin The receiver e-mail address
     * @param string $databaseReport1FileTarget The path to Database Report 1
     * @param string $platformReport1FileTarget The path to Platform Report 1
     */
    public function dispatchReports($toUser, $databaseReport1FileTarget, $platformReport1FileTarget)
    {
        $message = \Swift_Message::newInstance();
        $message->setSubject($this->reportSubject.' '.date('Y', strtotime('- 1 year')));
        $message->setFrom($this->adminEmail);
        $message->setTo($toUser);
        $message->setBody($this->templating->render('SubugoeCounterBundle:reports:emailbody.html.twig', ['reportBody' => $this->reportBody]), 'text/html');
        $message->attach(\Swift_Attachment::fromPath($databaseReport1FileTarget));
        $message->attach(\Swift_Attachment::fromPath($platformReport1FileTarget));
        $this->mailer->send($message);
    }
}
