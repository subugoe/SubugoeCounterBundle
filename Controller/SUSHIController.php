<?php

namespace Subugoe\CounterBundle\Controller;

use Subugoe\CounterBundle\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SUSHIController extends AbstractController
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

    /**
     * @var array An array containing matches to tracking abbreviation in Piwik database
     */
    protected $counterTerms = [
        'RS' => [0, self::REGULAR_SEARCHES, 'Searches'],
        'SFA' => [1, self::FEDERATED_AUTOMATED_SEARCHES, 'Searches'],
        'RV' => [2, self::RECORD_VIEWS, 'Requests'],
        'RC' => [3, self::RESULT_CLICKS, 'Requests'],
    ];

    /**
     * @var ReportService
     */
    private $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Returns Database Report 1 in json format.
     *
     * @param $request The request
     *
     * @return array $databaseReport1 The Database Report 1
     */
    public function databaseReport1Action(Request $request): Response
    {
        $customerid = $request->get('customerid');
        $beginDate = $request->get('beginDate');
        $endDate = $request->get('endDate');

        $period = null;
        if (!empty($beginDate) && !empty($endDate)) {
            $period = $beginDate.','.$endDate;
        }

        $databaseReport1Data = null;
        if ($customerid && $period) {
            $databaseReport1Data = $this->reportService->getDatabaseReport1Data($customerid, $period);
        }

        $dr1 = [
            'ReportHeader' => [
                'Created' => date('Y-m-d'),
                'CustomerID' => $customerid,
                'Report_ID' => 'DR1',
                'Release' => 5,
                'Report_Name' => 'Database Report 1',
                'Report_Description' => 'Database Report 1',
                'Institution_Name' => 'Name of the customer',
                'Institution_ID' => [],
                'Report_Filters' => [],
                'Report_Attributes' => [],
                'Exceptions' => [],
            ],
        ];

        foreach ($databaseReport1Data as $key => $value) {
            $instance = [];

            foreach ($value as $key1 => $value1) {
                $instance[] = ['MetricType' => $this->counterTerms[$key1][1], 'Count' => $value1];

                $dr1['ReportItems'][] = [
                    'Database' => $key,
                    'Platform' => 'nl.sub.uni-goettingen.de',
                    'Publisher' => 'SUB',
                    'Publisher_ID' => [],
                    'Data_Type' => 'Database',
                    'YOP' => '',
                    'Access_Type' => 'Controlled',
                    'Access_Method' => 'Regular',
                    'Performance' => [
                        'Period' => [
                            'Begin' => $beginDate,
                            'End' => $endDate,
                        ],
                        'Category' => $this->counterTerms[$key1][2],
                        'Section_Type' => '',
                        'YOP' => '',
                        'Access_Type' => 'Controlled',
                        'Access_Method' => 'Regular',
                        'Is_Archive' => 'N',
                        'Instance' => $instance,
                    ],
                ];
            }
        }

        return $this->json($dr1);
    }

    /**
     * Homepage of COUNTER Reports.
     */
    public function indexAction()
    {
        return $this->render('@SubugoeCounter/Default/index.html.twig');
    }

    /**
     * Returns Platform Report 1 in json format.
     *
     * @param $request
     *
     * @return array $platformReport1 The Platform Report 1
     */
    public function platformReport1Action(Request $request): Response
    {
        $customerid = $request->get('customerid');
        $beginDate = $request->get('beginDate');
        $endDate = $request->get('endDate');

        $period = null;
        if (!empty($beginDate) && !empty($endDate)) {
            $period = $beginDate.','.$endDate;
        }

        $platformReport1Data = null;
        if ($customerid && $period) {
            $platformReport1Data = $this->reportService->getPlatformReport1Data($customerid, $period);
        }

        $pr1 = [
            'ReportHeader' => [
                'Created' => date('Y-m-d'),
                'CustomerID' => $customerid,
                'Report_ID' => 'PR1',
                'Release' => 5,
                'Report_Name' => 'Platform Report 1',
                'Report_Description' => 'Platform Report 1',
                'Institution_Name' => 'Name of the customer',
                'Institution_ID' => [],
                'Report_Filters' => [],
                'Report_Attributes' => [],
                'Exceptions' => [],
            ],
        ];

        foreach ($platformReport1Data as $key => $value) {
            $instance = [];

            $instance[] = ['MetricType' => $this->counterTerms[$key][1], 'Count' => $value];

            $pr1['ReportItems'][] = [
                'Platform' => 'nl.sub.uni-goettingen.de',
                'Publisher' => 'SUB',
                'Publisher_ID' => [],
                'Data_Type' => 'Platform',
                'YOP' => '',
                'Access_Type' => 'Controlled',
                'Access_Method' => 'Regular',
                'Performance' => [
                    'Period' => [
                        'Begin' => $beginDate,
                        'End' => $endDate,
                    ],
                    'Category' => $this->counterTerms[$key][2],
                    'Section_Type' => '',
                    'YOP' => '',
                    'Access_Type' => 'Controlled',
                    'Access_Method' => 'Regular',
                    'Is_Archive' => 'N',
                    'Instance' => $instance,
                ],
            ];
        }

        return $this->json($pr1);
    }

    public function reportsAction(Request $request)
    {
        $search = $request->get('search');
        $customerid = $request->get('customerid');

        $response = new Response();
        $response->setContent('This should return report types available. If search is given then it should be checked if the requested report type is available.');
        $response->headers->set('Content-Type', 'text/plain');
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }
}
