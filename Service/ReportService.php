<?php

namespace Subugoe\CounterBundle\Service;

use symfony\Component\Filesystem\Filesystem;
use Symfony\Bundle\TwigBundle\TwigEngine;
use GuzzleHttp\Client as Guzzle;
use Solarium\Client;

/**
 * Service for generating counter reports.
 */
class ReportService
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Guzzle
     */
    protected $guzzle;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var TwigEngine
     */
    protected $templating;

    /*
     * @var UserService
     */
    private $userService;

    /*
     * @var DocumentService
     */
    private $documentService;

    /*
     * @var int Piwik idsite
     */
    private $idsite;

    /*
     * @var string Piwik token
     */
    private $token;

    /*
     * @var string Reporting platform name
     */
    private $platform;

    /*
     * @var array An array containing document databases
     */
    private $counterCollections;

    /*
     * @var string The reporting format
     */
    const REPORT_FORMAT = 'xls';

    /*
     * @var string The name under which database report 1 tracking data are stored in Piwik database
     */
    const DATABASE_REPORT_1 = 'databaseReport1';

    /*
     * @var string The name under which platform report 1 tracking data are stored in Piwik database
     */
    const PLATFORM_REPORT_1 = 'platformReport1';

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
     * @var string The name of Piwik function used for requesting Piwik slot indexs
     */
    const GET_CUSTOM_VARIABLES = 'getCustomVariables';

    /*
     * @var string The format of delivered data
     */
    const DATA_FORMAT = 'format=php';

    /*
     * @var string Reporting period
     */
    const REPORT_PERIOD = 'period=range';

    /*
     * @var string The name of Piwik module used for requesting tracking data
     */

    const MODULE = 'module=API';

    /*
     * @var array An array containing matches to tracking abbreviation in Piwik database
     */
    protected $counterTerms = ['RS' => [0, self::REGULAR_SEARCHES], 'SFA' => [1, self::FEDERATED_AUTOMATED_SEARCHES], 'RV' => [2, self::RECORD_VIEWS], 'RC' => [3, self::RESULT_CLICKS]];

    /**
     * ReportService constructor.
     *
     * @param Client          $client
     * @param Guzzle          $guzzle
     * @param Filesystem      $filesystem
     * @param TwigEngine      $templating
     * @param UserService     $userService
     * @param DocumentService $documentService
     */
    public function __construct(Client $client, Guzzle $guzzle, Filesystem $filesystem, TwigEngine $templating, UserService $userService, DocumentService $documentService)
    {
        $this->client = $client;
        $this->guzzle = $guzzle;
        $this->filesystem = $filesystem;
        $this->templating = $templating;
        $this->userService = $userService;
        $this->documentService = $documentService;
    }

    public function setConfig($idsite, $token, $platform, $counterCollections)
    {
        $this->idsite = $idsite;
        $this->token = $token;
        $this->platform = $platform;
        $this->counterCollections = $counterCollections;
    }

    /**
     * Returns tha data needed for generating COUNTER database report 1.
     *
     * @return array $databaseReport1Data The Database Report 1 data
     * @return array $platformReport1data The Platform Report 1 data
     * @return array $reportingPeriod The reporting period
     */
    public function reportService($month, $year)
    {
        $allUsers = $this->userService->getUsers();

        $databaseReport1Type = self::DATABASE_REPORT_1;
        $platformReport1Type = self::PLATFORM_REPORT_1;

        if (!empty($month) && !empty($year)) {
            $currentMonth = $month;
            $currentYear = $year;
        } else {

            $currentMonth = intval(ltrim(date('m'), '0'));
            $currentYear = date('Y');

            if ($currentMonth === 1) {
                $currentMonth = 12;
                $currentYear = date('Y', strtotime('-1 year'));
            } else {
                $currentMonth = $currentMonth - 1;
            }
        }

        $coveredPeriodStart = $currentYear.'-01-01';
        $firstOfTheEndMonth = $currentYear.'-'.$currentMonth.'-01';
        $coveredPeriodEnd = date('Y-m-t',strtotime($firstOfTheEndMonth));

        $reportingPeriod = [];
        $databaseReport1Data = [];
        $platformReport1Data = [];
        $databaseReport1Content = [];
        $platformReport1Content = [];

        $availableProducts = $this->documentService->getAvailableProducts();

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
                    $userproducts = $this->userService->getUserProducts($identifier);
                    $reportProducts = array_intersect($userproducts, $availableProducts);
                    $areThereReportProducts = ($reportProducts !== []) ? true : false;
                    $userVisitsArr = $this->getDatabaseData($databaseReport1Content, $identifier);

                    foreach ($userVisitsArr as $product => $value) {
                        if (in_array($product, $reportProducts)) {
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

        return [$databaseReport1Data, $platformReport1Data, $reportingPeriod, $coveredPeriodStart, $coveredPeriodEnd];
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
     * Generates Database Report  1
     *
     * @param string $userIdentifier The user identifier
     * @param string $reportsDir The reports dir
     * @param array $data The user specific tracked data for Database Report 1
     * @param array $reportingPeriod The reporting period
     *
     * @return string $databaseReport1FileTarget The path to Database Report 1 file
     */
    public function generateDatabaseReport1($userIdentifier, $reportsDir, $data, $reportingPeriod, $coveredPeriodStart, $coveredPeriodEnd)
    {
        $collections = $this->counterCollections;
        $publisherArr = array_combine(array_column($collections, 'id'), array_column($collections, 'publisher'));
        $fulltitleArr = array_combine(array_column($collections, 'id'), array_column($collections, 'full_title'));
        $platform = $this->platform;
        $databaseReport1FileName = self::DATABASE_REPORT_1.'_'.$userIdentifier.'.'.self::REPORT_FORMAT;
        $databaseReport1FileTarget = $reportsDir.$databaseReport1FileName;
        $this->filesystem->dumpFile(
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
                                'coveredPeriodStart' => $coveredPeriodStart,
                                'coveredPeriodEnd' => $coveredPeriodEnd,
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
    public function generatePlatformReport1($userIdentifier, $reportsDir, $user, $platformReport1data, $reportingPeriod, $coveredPeriodStart, $coveredPeriodEnd)
    {
        $platform = $this->platform;
        $platformReport1FileName1 = self::PLATFORM_REPORT_1.'_'.$userIdentifier.'.'.self::REPORT_FORMAT;
        $platformReport1FileTarget = $reportsDir.$platformReport1FileName1;
        $this->filesystem->dumpFile(
                $platformReport1FileTarget,
                $this->templating->render(
                        'SubugoeCounterBundle:reports:platformreport1.xls.twig',
                        [
                                'platformreport1' => $platformReport1data,
                                'customer' => $user,
                                'customerIdentifier' => $userIdentifier,
                                'reportingPeriod' => $reportingPeriod,
                                'platform' => $platform,
                                'coveredPeriodStart' => $coveredPeriodStart,
                                'coveredPeriodEnd' => $coveredPeriodEnd,
                        ]
                )
        );

        return $platformReport1FileTarget;
    }

    /*
     * Returns the Database Report 1 data for a given user and month
     *
     * @param array $databaseReport1Content The tracked data
     * @param string $identifier The user identifier
     *
     * @return array $databaseReport1Data The Database Report 1 visit data
     */
    protected function getDatabaseData($databaseReport1Content, $identifier)
    {
        $userVisitsArr = [];

        if (is_array($databaseReport1Content) && $databaseReport1Content !== []) {
            foreach ($databaseReport1Content as $key => $item) {
                $trackingString = explode(':', $item['label']);

                if ($trackingString[1]) {
                    $userTrackedIdentifier = $trackingString[1];
                }

                if (isset($userTrackedIdentifier) && $userTrackedIdentifier === $identifier) {
                    if (!empty($trackingString[2])) {
                        $trackedUserVisitsArr[$trackingString[2]][$trackingString[0]] = $item['nb_actions'];
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
                    $trackedUserVisitsArr[$trackingString[0]] = $item['nb_actions'];
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
    protected function getProductsTracked($databaseReport1Content, $identifier)
    {
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
        $client = $this->guzzle;
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
        $moduleStr = self::MODULE;
        $methodStr = 'method=CustomVariables.'.$method;
        $idsiteStr = sprintf('idSite=%d', $this->idsite);
        $periodStr = self::REPORT_PERIOD;
        $dateStr = sprintf('date=%s', $period);
        $formatStr = self::DATA_FORMAT;
        $tokenAuthStr = sprintf('token_auth=%s', $this->token);
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
        $filterLimit = 'filter_limit=-1';
        $uri .= '&'.$filterLimit;
        $client = $this->guzzle;
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
}
