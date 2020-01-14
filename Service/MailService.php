<?php

namespace Subugoe\CounterBundle\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Service for sending generated counter reports.
 */
class MailService
{
    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var string Admin E-mail
     */
    private $adminEmail;

    /**
     * @var string Cumulative Report E-Mail body
     */
    private $cumulativeReportBody;

    /**
     * @var string Cumulative Report E-Mail subject
     */
    private $cumulativeReportSubject;

    /**
     * @var string Number of reports sent
     */
    private $numberOfReportsSent;

    /**
     * @var string Report E-Mail body
     */
    private $reportBody;

    /**
     * @var string Reporting service end E-mail body
     */
    private $reportingEndBody;

    /**
     * @var string Reporting service end E-mail subject
     */
    private $reportingEndSubject;

    /**
     * @var string Reporting service start E-mail body
     */
    private $reportingStartBody;

    /**
     * @var string Reporting service start E-mail subject
     */
    private $reportingStartSubject;

    /**
     * @var string Report E-Mail subject
     */
    private $reportSubject;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Dispatches the generated cumulative database report 1 via e-mail.
     *
     * @param string $databaseReport1FileTarget The path to Database Report 1
     */
    public function dispatchCumulativeDatabaseReport(string $databaseReport1FileTarget, ?int $year): void
    {
        $dispatchMessage = new Email();
        $dispatchMessage
            ->subject($this->cumulativeReportSubject.' '.$year)
            ->from($this->adminEmail)
            ->to($this->adminEmail)
            ->text($this->cumulativeReportBody.' '.$year.'.')
            ->attachFromPath($databaseReport1FileTarget);

        $this->mailer->send($dispatchMessage);
    }

    /**
     * Dispatches the generated reports via e-mail.
     *
     * @param string $toAdmin                   The receiver e-mail address
     * @param string $databaseReport1FileTarget The path to Database Report 1
     * @param string $platformReport1FileTarget The path to Platform Report 1
     */
    public function dispatchReports(?string $toUser, ?string $databaseReport1FileTarget, ?string $platformReport1FileTarget, $database, $platform): void
    {
        $message = new TemplatedEmail();
        $message->subject($this->reportSubject.' '.date('Y', strtotime('- 1 year')))
                ->from($this->adminEmail)
                ->to($toUser)
                ->htmlTemplate('@SubugoeCounter/reports/emailbody.html.twig')
                ->context([
                    'reportBody' => $this->reportBody,
                ]);

        if (1 === (int) $database) {
            $message->attachFromPath($databaseReport1FileTarget);
        }
        if (1 === (int) $platform) {
            $message->attachFromPath($platformReport1FileTarget);
        }

        $this->mailer->send($message);
    }

    /**
     * Informs the Admin of the end of report service via e-mail.
     *
     * @param string $customersInformed The e-mail body
     * @param int    $si                The number of users informed
     */
    public function informAdminEnd($customersInformed, $i): void
    {
        $adminMessageEnd = new Email();
        $adminMessageEnd->subject($this->reportingEndSubject)
                        ->from($this->adminEmail)
                        ->to($this->adminEmail)
                        ->text($this->reportingEndBody.' '.date('d.m-Y H:i:s')."\r\n\r\n ".$this->numberOfReportsSent.' '.$i."\r\n\r\n".$customersInformed);

        $this->mailer->send($adminMessageEnd);
    }

    /**
     * Informs the Admin of the start of report service via e-mail.
     */
    public function informAdminStart(): void
    {
        $adminMessageStart = new Email();
        $adminMessageStart
            ->subject($this->reportingStartSubject)
            ->from($this->adminEmail)
            ->to($this->adminEmail)
            ->text($this->reportingStartBody.' '.date('d.m-Y H:i:s'));

        $this->mailer->send($adminMessageStart);
    }

    public function setConfig($adminEmail, $reportSubject, $reportBody, $reportingStartSubject, $reportingStartBody, $reportingEndSubject, $reportingEndBody, $numberOfReportsSent, $cumulativeReportSubject, $cumulativeReportBody)
    {
        $this->adminEmail = $adminEmail;
        $this->reportSubject = $reportSubject;
        $this->reportBody = $reportBody;
        $this->reportingStartSubject = $reportingStartSubject;
        $this->reportingStartBody = $reportingStartBody;
        $this->reportingEndSubject = $reportingEndSubject;
        $this->reportingEndBody = $reportingEndBody;
        $this->numberOfReportsSent = $numberOfReportsSent;
        $this->cumulativeReportSubject = $cumulativeReportSubject;
        $this->cumulativeReportBody = $cumulativeReportBody;
    }
}
