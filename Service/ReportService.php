<?php

namespace Subugoe\CounterBundle\Service;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Subugoe\CounterBundle\Entity\User as User;
use Solarium\Client;

/**
 * Service for generating counter reports.
 */
class ReportService
{
    /**
     * @var RegistryInterface
     */
    protected $doctrine;

    /**
     * @var RequestStack
     */
    protected $request;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var
     */
    protected $templating;

    const REPORT_FORMAT = 'xls';

    const BOOK_REPORT_1 = 'bookReport1';

    const DATABASE_REPORT_1 = 'databaseReport1';

    const PLATFORM_REPORT_1 = 'platformReport1';

    const REGULAR_SEARCHES = 'Regular Searches';

    const FEDERATED_AUTOMATED_SEARCHES = 'Searches-federated and automated';

    const RECORD_VIEWS = 'Record Views';

    const RESULT_CLICKS = 'Result Clicks';

    const GET_CUSTOM_VARIABLES = 'getCustomVariables';

    const DATA_FORMAT = 'format=php';

    const REPORT_PERIOD = 'period=range';

    const MODULE = 'module=API';

    protected $counterTerms = ['RS' => [0, self::REGULAR_SEARCHES], 'SFA' => [1, self::FEDERATED_AUTOMATED_SEARCHES], 'RV' => [2, self::RECORD_VIEWS], 'RC' => [3, self::RESULT_CLICKS]];

    /**
     * ReportService constructor.
     *
     * @param RegistryInterface $doctrine
     * @param RequestStack      $request
     * @param Container         $container
     */
    public function __construct(RegistryInterface $doctrine, RequestStack $request, Container $container, Client $client)
    {
        $this->doctrine = $doctrine;
        $this->request = $request;
        $this->container = $container;
        $this->client = $client;
        $this->templating = $container->get('templating');
    }

    /**
     * Returns tha data needed for generating COUNTER database report 1.
     *
     * @return array $databaseReport1Data The Database Report 1 data
     * @return array $platformReport1data The Platform Report 1 data
     * @return array $reportingPeriod The reporting period
     */
    public function reportService()
    {
        $allUsers = $this->getUsers();

        $databaseReport1Type = self::DATABASE_REPORT_1;
        $platformReport1Type = self::PLATFORM_REPORT_1;
        $currentMonth = intval(ltrim(date('m'), '0'));
        $currentYear = date('Y');

        if ($currentMonth === 1) {
            $currentMonth = 12;
            $currentYear = date('Y', strtotime('-1 year'));
        } else {
            $currentMonth = $currentMonth - 1;
        }

        $reportingPeriod = [];
        $databaseReport1Data = [];
        $platformReport1Data = [];
        $databaseReport1Content = [];
        $platformReport1Content = [];

        $availableProducts = $this->getAvailableProducts();

        for ($i = 1; $i <= $currentMonth; ++$i) {
            $startMonth = $i;
            if ($i < 10) {
                $startMonth = '0'.$i;
            }

            $startDate = $currentYear.'-'.$startMonth.'-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            $month = date('M', strtotime($startDate));
            $singleReportPeriod = $month.'-'.$currentYear;
            $period = $startDate.','.$endDate;

            $databaseReport1IdSubtable = $this->getIdSubtable($databaseReport1Type, $period);

            $platformReport1IdSubtable = $this->getIdSubtable($platformReport1Type, $period);

            if ($databaseReport1IdSubtable !== null) {
                $databaseReport1Content = $this->getTrackingData($databaseReport1IdSubtable, $period);
            }

            if ($platformReport1IdSubtable !== null) {
                $platformReport1Content = $this->getTrackingData($platformReport1IdSubtable, $period);
            }

            $reportingPeriod[] = $singleReportPeriod;

            if (is_array($allUsers) && $allUsers !== []) {
                foreach ($allUsers as $identifier => $institution) {
                    $userproducts = $this->getUserProducts($identifier);
                    $reportProducts = array_intersect($userproducts, $availableProducts);
                    $areThereReportProducts = ($reportProducts !== []) ? true : false;
                    $userVisitsArr = $this->getDatabaseData($databaseReport1Content, $identifier);

                    foreach ($userVisitsArr as $product => $value) {
                        if (in_array($product, $reportProducts)){
                            foreach ($value as $key => $visitsCount) {
                                $databaseReport1Data[$identifier][$institution][$product][$this->counterTerms[$key][1]][$i] = $visitsCount;
                            }
                        }
                    }

                    $productsTracked = $this->getProductsTracked($databaseReport1Content, $identifier);
                    $productsNotTracked = array_diff($reportProducts, $productsTracked);

                    if (is_array($productsNotTracked) && $productsNotTracked !== []) {
                        foreach ($productsNotTracked as $productNotTracked) {
                            $databaseReport1Data[$identifier][$institution][$productNotTracked][self::REGULAR_SEARCHES][$i] = 0;
                            $databaseReport1Data[$identifier][$institution][$productNotTracked][self::FEDERATED_AUTOMATED_SEARCHES][$i] = 0;
                            $databaseReport1Data[$identifier][$institution][$productNotTracked][self::RECORD_VIEWS][$i] = 0;
                            $databaseReport1Data[$identifier][$institution][$productNotTracked][self::RESULT_CLICKS][$i] = 0;
                        }
                    }

                    $pr1UserVisitsArr = $this->getPlatformData($platformReport1Content, $identifier);

                    if (is_array($pr1UserVisitsArr) && $pr1UserVisitsArr !== []) {
                        foreach ($pr1UserVisitsArr as $key => $pr1VisitsCount) {
                            $platformReport1Data[$identifier][$institution][$this->counterTerms[$key][1]][$i] = $pr1VisitsCount;
                        }
                    } else {
                        if ($areThereReportProducts) {
                            $abbrCounterTerms = array_keys($this->counterTerms);

                            foreach ($abbrCounterTerms as $key => $value) {
                                $platformReport1Data[$identifier][$institution][$this->counterTerms[$value][1]][$i] = 0;
                            }
                        }
                    }
                }
            }
        }

        return [$databaseReport1Data, $platformReport1Data, $reportingPeriod];
    }

    /*
     * Returns the Database Report 1 data for a given user and month
     *
     * @param array $databaseReport1Content The tracked data
     * @param string $identifier The user identifier
     *
     * @return array $databaseReport1Data The Database Report 1 visit data
     */
    protected function getDatabaseData($databaseReport1Content, $identifier) {
        $userVisitsArr = [];

        if (is_array($databaseReport1Content) && $databaseReport1Content !== []) {
            foreach ($databaseReport1Content as $key => $item) {
                $trackingString = explode(':', $item['label']);

                if ($trackingString[1]) {
                    $userTrackedIdentifier = $trackingString[1];
                }

                if (isset($userTrackedIdentifier) && $userTrackedIdentifier === $identifier) {
                    if (!empty($trackingString[2])) {
                        $trackedUserVisitsArr[$trackingString[2]][$trackingString[0]] = $item['nb_visits'];
                    }
                }
            }

            if (isset($trackedUserVisitsArr) && is_array($trackedUserVisitsArr) && $trackedUserVisitsArr != []) {
                $abbrCounterTerms = array_keys($this->counterTerms);

                foreach ($trackedUserVisitsArr as $productTracked => $productTrackedArr) {
                    foreach ($abbrCounterTerms as $abbrCounterTerm) {
                        $trackedVisitsCount = $this->findValue($productTrackedArr, $abbrCounterTerm);
                        if (!$trackedVisitsCount) {
                            $userVisitsArr[$productTracked][$abbrCounterTerm] = 0;
                        } else {
                            $userVisitsArr[$productTracked][$abbrCounterTerm] = $trackedVisitsCount;
                        }
                    }
                }
            }
        }

        return $userVisitsArr;
    }

    /*
     * Returns Database Report 1 data
     *
     * @param string $identifier The user identifier
     * @param string $period The report period
     *
     * @return array $databaseReport1 The Database Report 1 data
     */
    public function getDatabaseReport1Data($identifier, $period)
    {
        $databaseReport1Content = [];
        $databaseReport1Type = self::DATABASE_REPORT_1;
        $databaseReport1IdSubtable = $this->getIdSubtable($databaseReport1Type, $period);

        if ($databaseReport1IdSubtable !== null) {
            $databaseReport1Content = $this->getTrackingData($databaseReport1IdSubtable, $period);
        }

        $databaseReport1Data = $this->getDatabaseData($databaseReport1Content, $identifier);

        return $databaseReport1Data;
    }

    /*
     * Returns Platform Report 1 data
     *
     * @param string $identifier The user identifier
     * @param string $period The report period
     *
     * @return array $platformReport1 The Platform Report 1 data
     */
    public function getPlatformReport1Data($identifier, $period)
    {
        $platformReport1Content = [];
        $platformReport1Type = self::PLATFORM_REPORT_1;
        $platformReport1IdSubtable = $this->getIdSubtable($platformReport1Type, $period);

        if ($platformReport1IdSubtable !== null) {
            $platformReport1Content = $this->getTrackingData($platformReport1IdSubtable, $period);
        }

        $platformReport1Data = $this->getPlatformData($platformReport1Content, $identifier);

        return $platformReport1Data;
    }

    /*
     * Returns the Platform Report 1 data for a given user and month
     *
     * @param array $platformReport1Content The tracked Platform Report 1 data
     * @param string $identifier The user identifier
     *
     * @return array $pr1UserVisitsArr The Platform Report 1 visit data
     */
    protected function getPlatformData($platformReport1Content, $identifier)
    {
        $pr1UserVisitsArr = [];

        if (is_array($platformReport1Content) && $platformReport1Content !== []) {
            foreach ($platformReport1Content as $key => $item) {
                $trackingString = explode(':', $item['label']);

                if ($trackingString[1]) {
                    $userTrackedIdentifier = $trackingString[1];
                }

                if (isset($userTrackedIdentifier) && $userTrackedIdentifier === $identifier) {
                    $trackedUserVisitsArr[$trackingString[0]] = $item['nb_visits'];
                }
            }

            if (isset($trackedUserVisitsArr) && is_array($trackedUserVisitsArr) && $trackedUserVisitsArr != []) {
                $abbrCounterTerms = array_keys($this->counterTerms);

                foreach ($abbrCounterTerms as $key => $value) {
                    if (array_key_exists($value, $trackedUserVisitsArr)) {
                        $pr1UserVisitsArr[$value] = $trackedUserVisitsArr[$value];
                    } else {
                        $pr1UserVisitsArr[$value] = 0;
                    }
                }
            }
        }

        return $pr1UserVisitsArr;
    }

    /*
     * Returns the list of products being tracked
     *
     * @param array $databaseReport1Content The tracked data
     * @param string $identifier The user identifier
     *
     * @return array $productsTracked The tracked products
     */
    protected function getProductsTracked($databaseReport1Content, $identifier) {
        $productsTracked = [];

        if (is_array($databaseReport1Content) && $databaseReport1Content !== []) {
            foreach ($databaseReport1Content as $key => $item) {
                $trackingString = explode(':', $item['label']);

                if ($trackingString[1]) {
                    $userTrackedIdentifier = $trackingString[1];
                }

                if (isset($userTrackedIdentifier) && $userTrackedIdentifier === $identifier) {
                    if (!empty($trackingString[2])) {
                        if (!in_array($trackingString[2], $productsTracked)) {
                            $productsTracked[] = $trackingString[2];
                        }

                    }
                }
            }
        }

        return $productsTracked;
    }

    /**
     * Returns the list of user products.
     *
     * @param string $identifier The user identifier
     *
     * @return array $userProducts The user products
     */
    protected function getUserProducts($identifier)
    {
        $userRepository = $this->doctrine->getRepository('Subugoe\CounterBundle\Entity\User');
        $userproducts = $userRepository->getUserProducts($identifier);
        $userproducts = array_unique($userproducts, SORT_REGULAR);
        $userproducts = array_column($userproducts, 'product');

        return $userproducts;
    }

    /**
     * Returns the list of all registered users.
     *
     * @return array $registeredUsers The list of all registered users
     */
    protected function getUsers()
    {
        $userRepository = $this->doctrine->getRepository('Subugoe\CounterBundle\Entity\User');
        $allUsersData = $userRepository->getUsers();
        $registeredUsers = $this->getUniqueUsers($allUsersData);

        return $registeredUsers;
    }

    /**
     * Returns the unique list of all registered users.
     *
     * @param array $allUsersData The user data
     *
     * @return array $allUniqueUsers The unique list of all registered users
     */
    protected function getUniqueUsers($allUsersData)
    {
        $allUniqueUsers = [];
        foreach ($allUsersData as $k => $userData) {
            if (!array_key_exists($userData->getIdentifier(), $allUniqueUsers)) {
                $allUniqueUsers[$userData->getIdentifier()] = $userData->getInstitution();
            }
        }

        return $allUniqueUsers;
    }

    /*
     * Returns the id for getting the tracking data for the specified report type
     *
     * @param string $reportType The report type
     * @param string $period The date range
     *
     * @param int $idSubtable The id of sub-data table
     */
    protected function getIdSubtable($reportType, $period)
    {
        $method = self::GET_CUSTOM_VARIABLES;
        $uri = $this->getPiwikUri($method, $period);
        $client = $this->container->get('guzzle.client.piwikreporter');
        $content = unserialize($client->get($uri)->getBody()->__toString());
        $idSubtable = null;

        foreach ($content as $item) {
            if ($item['label'] === $reportType) {
                $idSubtable = $item['idsubdatatable'];
            }
        }

        return $idSubtable;
    }

    /*
     * Builds and returns the uri needed for requesting tracking data
     *
     * @param string $method The requesting method
     *
     * @return string $uri The requesting uri
     */
    protected function getPiwikUri($method, $period = '')
    {
        $token_auth = $this->container->getParameter('piwik_token_auth');
        $moduleStr = self::MODULE;
        $methodStr = 'method=CustomVariables.'.$method;
        $idsiteStr = sprintf('idSite=%d', $this->container->getParameter('piwik_idsite'));
        $periodStr = self::REPORT_PERIOD;
        $dateStr = sprintf('date=%s', $period);
        $formatStr = self::DATA_FORMAT;
        $tokenAuthStr = sprintf('token_auth=%s', $token_auth);
        $uri = sprintf('?%s&%s&%s&%s&%s&%s&%s', $moduleStr, $methodStr, $idsiteStr, $periodStr, $dateStr, $formatStr, $tokenAuthStr);

        return $uri;
    }

    /*
     * Returns tracking data for the specified report type
     *
     * @param string $reportType The report type
     *
     * @return array $trackingData The tracking data
     */
    protected function getTrackingData($idSubtable, $period)
    {
        $method = 'getCustomVariablesValuesFromNameId';
        $uri = $this->getPiwikUri($method, $period);
        $idSubtable = 'idSubtable='.$idSubtable;
        $uri .= '&'.$idSubtable;
        $client = $this->container->get('guzzle.client.piwikreporter');
        $trackingData = unserialize($client->get($uri)->getBody()->__toString());

        return $trackingData;
    }

    /*
     * Returns the value of a given key in an array
     *
     * @param array $array The array
     * @param string $key The key
     *
     * @return string/boolean $value The value
     */
    protected function findValue($array, $keySearch)
    {
        foreach ($array as $key => $value) {
            if ($key === $keySearch) {
                return $value;
            }
        }

        return false;
    }

    /*
     * Returns the already indexed products from solr server
     *
     * @return array $products The product list
     */
    public function getAvailableProducts()
    {
        $query = $this->client->createSelect();
        $query->setFields(['product']);
        $query->addParam('group', true);
        $query->addParam('group.field', 'product');
        $query->addParam('group.main', true);
        $resultset = $this->client->select($query)->getDocuments();
        $products = array_column($resultset, 'product');

        return $products;
    }

    /**
     * Returns the document data needed for tracking.
     *
     * @param string $searchStr
     * @param string $id
     * @param array  $documentFields
     *
     * @return \Solarium\QueryType\Select\Result\DocumentInterface
     */
    public function getDocument($searchStr, $id, $documentFields)
    {
        $select = $this->client->createSelect();
        $select->setQuery($searchStr.':'.$id);
        $select->setFields($documentFields);
        $document = $this->client->select($select);
        $document = $document->getDocuments();

        return $document[0];
    }

    /*
     * Returns the user identifier
     *
     * @Return string $identifier The user identifier
     */
    public function getUserIdentifier()
    {
        $clientIp = $this->request->getMasterRequest()->getClientIp();
        $userRepository = $this->doctrine->getRepository('Subugoe\CounterBundle\Entity\User');
        $user = $userRepository->getUserIdentifier(ip2long($clientIp));
        $identifier = $user['identifier'];

        return $identifier;
    }

    /*
     * Informs the Admin of the start of report service via e-mail
     */
    public function informAdminStart()
    {
        $reportingStartSubject = $this->container->getParameter('reporting_start_subject');
        $reportingStartBody = $this->container->getParameter('reporting_start_body');
        $fromAdmin = $this->container->getParameter('admin_nlh_email');
        $toAdmin = $this->container->getParameter('admin_nlh_email');

        $adminMessageStart = \Swift_Message::newInstance();
        $adminMessageStart->setSubject($reportingStartSubject);
        $adminMessageStart->setFrom($fromAdmin);
        $adminMessageStart->setTo($toAdmin);
        $adminMessageStart->setBody($reportingStartBody.' '.date('d.m-Y H:i:s'));
        $this->container->get('mailer')->send($adminMessageStart);
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
        $reportingEndSubject = $this->container->getParameter('reporting_end_subject');
        $reportingEndBody = $this->container->getParameter('reporting_end_body');
        $numberOfReportsSent = $this->container->getParameter('number_of_reports_sent');
        $fromAdmin = $this->container->getParameter('admin_nlh_email');
        $toAdmin = $this->container->getParameter('admin_nlh_email');

        $adminMessageEnd = \Swift_Message::newInstance();
        $adminMessageEnd->setSubject($reportingEndSubject);
        $adminMessageEnd->setFrom($fromAdmin);
        $adminMessageEnd->setTo($toAdmin);
        $adminMessageEnd->setBody($reportingEndBody.' '.date('d.m-Y H:i:s')."\r\n\r\n ".$numberOfReportsSent." ".$i."\r\n\r\n".$customersInformed);
        $this->container->get('mailer')->send($adminMessageEnd);
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
        $reportSubject = $this->container->getParameter('report_subject');
        $reportBody = $this->container->getParameter('report_body');
        $fromAdmin = $this->container->getParameter('admin_nlh_email');

        $message = \Swift_Message::newInstance();
        $message->setSubject($reportSubject.' '.date('m-Y', strtotime('- 1 month')));
        $message->setFrom($fromAdmin);
        $message->setTo($toUser);
        $message->setBody($this->templating->render('SubugoeCounterBundle:reports:emailbody.html.twig', ['reportBody' => $reportBody]), 'text/html');
        $message->attach(\Swift_Attachment::fromPath($databaseReport1FileTarget));
        $message->attach(\Swift_Attachment::fromPath($platformReport1FileTarget));
        $this->container->get('mailer')->send($message);
    }

    /*
     * Generates Database Report  1
     *
     * @param string $userIdentifier The user identifier
     * @param string $reportsDir The reports dir
     * @param array $data The user specific tracked data for Database Report 1
     * @param array $reportingPeriod The reporting period
     *
     * @return string $databaseReport1FileTarget The path to Database Report 1 file
     */
    public function generateDatabaseReport1($userIdentifier, $reportsDir, $data, $reportingPeriod)
    {
        $collections = $this->container->getParameter('counter_collections');
        $publisherArr = array_combine(array_column($collections, 'id'), array_column($collections, 'publisher'));
        $fulltitleArr = array_combine(array_column($collections, 'id'), array_column($collections, 'full_title'));
        $platform = $this->container->getParameter('nlh_platform');
        $fs = $this->container->get('filesystem');
        $databaseReport1FileName = self::DATABASE_REPORT_1.'_'.$userIdentifier.'.'.self::REPORT_FORMAT;
        $databaseReport1FileTarget = $reportsDir.$databaseReport1FileName;
        $fs->dumpFile(
                $databaseReport1FileTarget,
                $this->templating->render(
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
     *
     * @return string $platformReport1FileTarget The path to Platform Report 1 file
     */
    public function generatePlatformReport1($userIdentifier, $reportsDir, $user, $platformReport1data, $reportingPeriod)
    {
        $fs = $this->container->get('filesystem');
        $platform = $this->container->getParameter('nlh_platform');
        $platformReport1FileName1 = self::PLATFORM_REPORT_1.'_'.$userIdentifier.'.'.self::REPORT_FORMAT;
        $platformReport1FileTarget = $reportsDir.$platformReport1FileName1;
        $fs->dumpFile(
                $platformReport1FileTarget,
                $this->templating->render(
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
