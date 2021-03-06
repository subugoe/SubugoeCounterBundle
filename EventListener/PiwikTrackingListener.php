<?php

namespace Subugoe\CounterBundle\EventListener;

use GuzzleHttp\ClientInterface;
use Subugoe\CounterBundle\Service\DocumentService;
use Subugoe\CounterBundle\Service\UserService;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class PiwikTrackingListener
{
    /**
     * @var string The name under which book report 1 tracking data are stored in Piwik database
     */
    const BOOK_REPORT_1 = 'bookReport1';

    /**
     * @var int Piwik slot index for book report 1
     */
    const BOOK_REPORT_1_SLOT = 1;

    /**
     * @var string The name under which book report 2 tracking data are stored in Piwik database
     */
    const BOOK_REPORT_2 = 'bookReport2';

    /**
     * @var int Piwik slot index for book report 2
     */
    const BOOK_REPORT_2_SLOT = 2;

    /**
     * @var string The name under which database report 1 tracking data are stored in Piwik database
     */
    const DATABASE_REPORT_1 = 'databaseReport1';

    /**
     * @var int Piwik slot index for database report 1
     */
    const DATABASE_REPORT_1_SLOT = 3;

    /**
     * @var string Document id lable in Solr database
     */
    const ID_SEARCH_STR = 'id';

    /**
     * @var string The name under which journal report 1 tracking data are stored in Piwik database
     */
    const JOURNAL_REPORT_1 = 'journalReport1';

    /**
     * @var int Piwik slot index for journal report 1
     */
    const JOURNAL_REPORT_1_SLOT = 5;

    /**
     * @var string Pdf document type
     */
    const PDF_DOCUMENT_TYPE = 'pdf';

    /**
     * @var string The name under which platform report 1 tracking data are stored in Piwik database
     */
    const PLATFORM_REPORT_1 = 'platformReport1';

    /**
     * @var int Piwik slot index for platform report 1
     */
    const PLATFORM_REPORT_1_SLOT = 4;

    /**
     * @var string Document work id lable in Solr database
     */
    const WORK_SEARCH_STR = 'work';

    /**
     * @var Monograph document type
     */
    private $configMonographDocumentType;

    /**
     * @var Periodical document type
     */
    private $configPeriodicalDocumentType;

    /**
     * @var Query document fields
     */
    private $documentFields;

    /**
     * @var DocumentService
     */
    private $documentService;

    /**
     * @var IPs to be excluded from tracking
     */
    private $excludeIps;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var Piwik id
     */
    private $idsite;

    /**
     * @var Piwik baseurl
     */
    private $piwiktrackerBaseurl;

    /**
     * @var Piwik token
     */
    private $token;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * PiwikTrackingListener constructor.
     */
    public function __construct(DocumentService $documentService, UserService $userService)
    {
        $this->documentService = $documentService;
        $this->userService = $userService;
    }

    public function setParameters(string $piwiktrackerBaseurl, int $piwikIdSite, string $configMonographDocumentType, string $configPeriodicalDocumentType, array $documentFields, array $excludeIps): void
    {
        $this->piwiktrackerBaseurl = $piwiktrackerBaseurl;
        $this->idsite = $piwikIdSite;
        $this->configMonographDocumentType = $configMonographDocumentType;
        $this->configPeriodicalDocumentType = $configPeriodicalDocumentType;
        $this->documentFields = $documentFields;
        $this->excludeIps = $excludeIps;
    }

    public function setHttpClient(ClientInterface $client): void
    {
        $this->httpClient = $client;
    }

    /**
     * Tracks the data needed for generating COUNTER-Reports after a successful response.
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $clientIp = $event->getRequest()->getClientIp();
        $clientIp = "134.76.2.64";

        $passed = true;

        if (is_array($this->excludeIps) && $this->excludeIps != [] && !empty($clientIp)) {
            $clientIpLong = ip2long($clientIp);

            foreach ($this->excludeIps as $key => $excludeIp) {
                if (strstr($excludeIp, '-')) {
                    [$lowerIP, $upperIP] = explode('-', $excludeIp, 2);

                    $lowerRange = ip2long($lowerIP);
                    $upperRange = ip2long($upperIP);

                    if ($clientIpLong >= $lowerRange && $clientIpLong <= $upperRange) {
                        $passed = false;
                        break;
                    }
                } else {
                    $range = ip2long($excludeIp);

                    if ($clientIpLong === $range) {
                        $passed = false;
                        break;
                    }
                }
            }
        }

        if ($passed) {
            $isResponseSuccessful = $event->getResponse()->isSuccessful();

            if ($isResponseSuccessful) {
                if (!empty($this->piwiktrackerBaseurl)) {
                    $queryString = urldecode($event->getRequest()->getQueryString());
                    $isThisASearchResult = explode('=', explode('?', $queryString)[0]);
                    $action = $event->getRequest()->get('_route');

                    if (!empty($action) && ('_detail' === $action || '_search' === $action || '_search_advanced' === $action || '_download_pdf' === $action)) {
                        $piwikPing = false;
                        try {
                            $piwikPing = $this->httpClient->request('get');

                            if (200 === $piwikPing->getStatusCode()) {
                                $piwikPing = true;
                            }
                        } catch (\Exception $e) {
                        }

                        if ($piwikPing) {
                            $userIdentifier = $this->userService->getUserIdentifier();

                            if (!empty($userIdentifier)) {
                                $idsiteStr = sprintf('idsite=%d', $this->idsite);
                                $recStr = 'rec=1';

                                if ('_detail' === $action || '_download_pdf' === $action) {
                                    $documentFields = $this->documentFields;
                                    $id = $event->getRequest()->get('id');
                                    $isThisAPdfDownload = false;
                                    $pdfDocumentType = null;
                                    $documentId = null;
                                    $document = null;
                                    if ('_detail' === $action) {
                                        $documentId = $id;

                                        if (strchr($id, '|')) {
                                            $idArr = explode('|', $id);
                                            $documentId = $idArr[0];
                                            if (isset($idArr[1]) && !empty($idArr[1])) {
                                                $activeChapterId = $idArr[1];
                                            }
                                        }

                                        $idSearchStr = self::ID_SEARCH_STR;
                                        $document = $this->documentService->getDocument(
                                                $idSearchStr,
                                                $documentId,
                                                $documentFields
                                        );
                                    } elseif ('_download_pdf' === $action) {
                                        $pdfDocumentId = explode(':', $id);
                                        $workId = $pdfDocumentId[1];

                                        if (isset($pdfDocumentId[2]) && !empty($pdfDocumentId[2])) {
                                            $activeChapterId = $pdfDocumentId[2];
                                        }

                                        $isThisAPdfDownload = true;
                                        $pdfDocumentType = self::PDF_DOCUMENT_TYPE;
                                        $workSearchStr = self::WORK_SEARCH_STR;
                                        $document = $this->documentService->getDocument(
                                                $workSearchStr,
                                                $workId,
                                                $documentFields
                                        );
                                        if (isset($document->id)) {
                                            $documentId = $document->id;
                                        }
                                    }

                                    $page = $event->getRequest()->get('page');

                                    $documentType = isset($document->docstrct) ? $document->docstrct : $document->log_type[0];

                                    if (isset($document->product)) {
                                        $product = $document->product;
                                        $product = preg_replace('/.+-/', '', $product);
                                    }

                                    if ((isset($documentId) && !empty($documentId)) &&
                                            (isset($userIdentifier) && !empty($userIdentifier)) &&
                                            (isset($product) && !empty($product))
                                    ) {
                                        if (null === $page) {
                                            $searchFlag = false;

                                            if (!empty($isThisASearchResult[1]) && 'search' === str_replace(
                                                            '/',
                                                            '',
                                                            $isThisASearchResult[1]
                                                    )
                                            ) {
                                                $searchFlag = str_replace('/', '', $isThisASearchResult[1]);
                                            }

                                            if (isset($documentType) && $documentType === $this->configMonographDocumentType && !isset($activeChapterId)) {
                                                // Book Report 1 Tracking Identifier (br1 = Book Report 1)
                                                $br1TrackingIdentifier = sprintf(
                                                        '%s:%s:%s',
                                                        $userIdentifier,
                                                        $product,
                                                        $documentId
                                                );

                                                if ($searchFlag) {
                                                    // Adds Search Identifier to Book Report 1 Tracking Identifier
                                                    $br1TrackingIdentifier = sprintf(
                                                            $br1TrackingIdentifier.':%s',
                                                            $searchFlag
                                                    );
                                                }

                                                if ($isThisAPdfDownload) {
                                                    // Adds pdf-Document Identifier to Book Report 1 Tracking Identifier
                                                    $br1TrackingIdentifier = sprintf(
                                                            $br1TrackingIdentifier.':%s',
                                                            $pdfDocumentType
                                                    );
                                                }

                                                // Tracks Clicks for Book Report 1 (br1 = Book Report 1)
                                                if (!empty($br1TrackingIdentifier)) {
                                                    $br1CVAR = sprintf(
                                                            'cvar={"%d":["%s","%s"]}',
                                                            self::BOOK_REPORT_1_SLOT,
                                                            self::BOOK_REPORT_1,
                                                            $br1TrackingIdentifier
                                                    );
                                                    $br1TrackingRequest = sprintf(
                                                            '?%s&%s&%s',
                                                            $br1CVAR,
                                                            $idsiteStr,
                                                            $recStr
                                                    );
                                                    $br1Promise = $this->httpClient->postAsync($br1TrackingRequest);
                                                    $br1Promise->wait();
                                                }
                                            } elseif (isset($documentType) && $documentType === $this->configPeriodicalDocumentType) {
                                                // Journal Report 1 Tracking Identifier
                                                $jr1TrackingIdentifier = sprintf(
                                                        '%s:%s:%s',
                                                        $userIdentifier,
                                                        $product,
                                                        $documentId
                                                );

                                                if ($isThisAPdfDownload) {
                                                    // Adds pdf-Document Identifier to Journal Report 1 Tracking Identifier
                                                    $jr1TrackingIdentifier = sprintf(
                                                            $jr1TrackingIdentifier.':%s',
                                                            $pdfDocumentType
                                                    );
                                                }

                                                // Tracks Journal HTML and PDF Clicks for Journal Report 1 (jr1)
                                                if (!empty($jr1TrackingIdentifier)) {
                                                    $jr1CVAR = sprintf(
                                                            'cvar={"%d":["%s","%s"]}',
                                                            self::JOURNAL_REPORT_1_SLOT,
                                                            self::JOURNAL_REPORT_1,
                                                            $jr1TrackingIdentifier
                                                    );
                                                    $jr1TrackingRequest = sprintf(
                                                            '?%s&%s&%s',
                                                            $jr1CVAR,
                                                            $idsiteStr,
                                                            $recStr
                                                    );
                                                    $jr1Promise = $this->httpClient->postAsync($jr1TrackingRequest);
                                                    $jr1Promise->wait();
                                                }
                                            }

                                            if (!isset($activeChapterId)) {
                                                $referer = $event->getRequest()->headers->get('referer');
                                                $externalClick = false;
                                                if (null !== $referer) {
                                                    $refererHost = parse_url($referer)['host'];
                                                    $localHost = $event->getRequest()->getHost();

                                                    if ($refererHost !== $localHost) {
                                                        $externalClick = true;
                                                    }
                                                }

                                                // Tracking Record Views and Result Clicks for Database Report 1 and Platform Report 1

                                                // Database Report 1 Record Views Tracking Identifier (dr1 = Database Report 1, RV = Record Views)
                                                $dr1RVTrackingIdentifier = sprintf(
                                                        '%s:%s:%s',
                                                        'RV',
                                                        $userIdentifier,
                                                        $product
                                                );

                                                // Platform Report 1 Record Views Tracking Identifier (pr1 = Platform Report 1, RV = Record Views)
                                                $pr1RVTrackingIdentifier = sprintf('%s:%s', 'RV', $userIdentifier);

                                                if ($externalClick) {
                                                    // Database Report 1 Result Clicks Tracking Identifier (dr1 = Database Report 1, RC = Result Clicks)
                                                    $dr1RCTrackingIdentifier = sprintf(
                                                            '%s:%s:%s',
                                                            'RC',
                                                            $userIdentifier,
                                                            $product
                                                    );
                                                    // Platform Report 1 Result Clicks Tracking Identifier (pr1 = Platform Report 1, RC = Result Clicks)
                                                    $pr1RCTrackingIdentifier = sprintf('%s:%s', 'RC', $userIdentifier);
                                                }
                                                // Tracks Record Views for Database Report 1 (dr1 = Database Report 1, RV = Record Views)
                                                if (!empty($dr1RVTrackingIdentifier)) {
                                                    $dr1RVCVAR = sprintf(
                                                            'cvar={"%d":["%s","%s"]}',
                                                            self::DATABASE_REPORT_1_SLOT,
                                                            self::DATABASE_REPORT_1,
                                                            $dr1RVTrackingIdentifier
                                                    );
                                                    $dr1RVTrackingRequest = sprintf(
                                                            '?%s&%s&%s',
                                                            $dr1RVCVAR,
                                                            $idsiteStr,
                                                            $recStr
                                                    );
                                                    $dr1RVPromise = $this->httpClient->postAsync($dr1RVTrackingRequest);
                                                    $dr1RVPromise->wait();
                                                }

                                                // Tracks Result Clicks for Database Report 1 (dr1 = Database Report 1, RC = Result Clicks)
                                                if (isset($dr1RCTrackingIdentifier) && !empty($dr1RCTrackingIdentifier)) {
                                                    $dr1RCCVAR = sprintf(
                                                            'cvar={"%d":["%s","%s"]}',
                                                            self::DATABASE_REPORT_1_SLOT,
                                                            self::DATABASE_REPORT_1,
                                                            $dr1RCTrackingIdentifier
                                                    );
                                                    $dr1RCTrackingRequest = sprintf(
                                                            '?%s&%s&%s',
                                                            $dr1RCCVAR,
                                                            $idsiteStr,
                                                            $recStr
                                                    );
                                                    $dr1RCPromise = $this->httpClient->postAsync($dr1RCTrackingRequest);
                                                    $dr1RCPromise->wait();
                                                }

                                                // Tracks Record Views for Platform Report 1 (pr1 = Platform Report 1, RV = Record Views)
                                                if (!empty($pr1RVTrackingIdentifier)) {
                                                    $pr1RVcvar = sprintf(
                                                            'cvar={"%d":["%s","%s"]}',
                                                            self::PLATFORM_REPORT_1_SLOT,
                                                            self::PLATFORM_REPORT_1,
                                                            $pr1RVTrackingIdentifier
                                                    );
                                                    $pr1RVTrackingRequest = sprintf(
                                                            '?%s&%s&%s',
                                                            $pr1RVcvar,
                                                            $idsiteStr,
                                                            $recStr
                                                    );
                                                    $pr1RVPromise = $this->httpClient->postAsync($pr1RVTrackingRequest);
                                                    $pr1RVPromise->wait();
                                                }

                                                // Tracks Result Clicks for Platform Report 1 (pr1 = Platform Report 1, RC = Result Clicks)
                                                if (isset($pr1RCTrackingIdentifier) && !empty($pr1RCTrackingIdentifier)) {
                                                    $pr1RCCVAR = sprintf(
                                                            'cvar={"%d":["%s","%s"]}',
                                                            self::PLATFORM_REPORT_1_SLOT,
                                                            self::PLATFORM_REPORT_1,
                                                            $pr1RCTrackingIdentifier
                                                    );
                                                    $pr1RCTrackingRequest = sprintf(
                                                            '?%s&%s&%s',
                                                            $pr1RCCVAR,
                                                            $idsiteStr,
                                                            $recStr
                                                    );
                                                    $pr1RCTPromise = $this->httpClient->postAsync($pr1RCTrackingRequest);
                                                    $pr1RCTPromise->wait();
                                                }
                                            }
                                        }

                                        if (isset($activeChapterId) && !empty($activeChapterId) && isset($documentType) && $documentType === $this->configMonographDocumentType) {
                                            // Book Report 2 Tracking Identifier (br2 = Book Report 2)
                                            $br2TrackingIdentifier = sprintf(
                                                    '%s:%s:%s:%s',
                                                    $userIdentifier,
                                                    $product,
                                                    $documentId,
                                                    $activeChapterId
                                            );

                                            if ($isThisAPdfDownload) {
                                                // Adds pdf-Document Identifier to Journal Report 2 Tracking Identifier
                                                $br2TrackingIdentifier = sprintf(
                                                        $br2TrackingIdentifier.':%s',
                                                        $pdfDocumentType
                                                );
                                            }

                                            // Tracks Book Chapter Clicks for Book Report 2 (br2 = Book Report 2)
                                            if (!empty($br2TrackingIdentifier)) {
                                                $br2CVAR = sprintf(
                                                        'cvar={"%d":["%s","%s"]}',
                                                        self::BOOK_REPORT_2_SLOT,
                                                        self::BOOK_REPORT_2,
                                                        $br2TrackingIdentifier
                                                );
                                                $br2TrackingRequest = sprintf(
                                                        '?%s&%s&%s',
                                                        $br2CVAR,
                                                        $idsiteStr,
                                                        $recStr
                                                );
                                                $br2Promise = $this->httpClient->postAsync($br2TrackingRequest);
                                                $br2Promise->wait();
                                            }
                                        }
                                    }
                                } elseif ('_search' === $action || '_search_advanced' === $action) {
                                    switch ($action) {
                                        case '_search':
                                            $collection = $event->getRequest()->get('collection');
                                            break;
                                        case '_search_advanced':
                                            $collection = $event->getRequest()->get('advanced_search')['product'];
                                            break;
                                    }

                                    if (!empty($collection) && 'all' !== $collection) {
                                        // Database Report 1 Regular Searches Tracking Identifier (dr1 = Database Report 1, RS = Regular Searches)
                                        $dr1SearchTrackingIdentifier = sprintf(
                                                '%s:%s:%s',
                                                'RS',
                                                $userIdentifier,
                                                $collection
                                        );

                                        // Tracks Regular Searches for Database Report 1 (dr1 = Database Report 1)
                                        $dr1SearchCVAR = sprintf(
                                                'cvar={"%d":["%s","%s"]}',
                                                self::DATABASE_REPORT_1_SLOT,
                                                self::DATABASE_REPORT_1,
                                                $dr1SearchTrackingIdentifier
                                        );
                                        $dr1SearchTrackingRequest = sprintf(
                                                '?%s&%s&%s',
                                                $dr1SearchCVAR,
                                                $idsiteStr,
                                                $recStr
                                        );
                                        $dr1SearchPromise = $this->httpClient->postAsync($dr1SearchTrackingRequest);
                                        $dr1SearchPromise->wait();
                                    } else {
                                        $userProducts = $this->userService->getUserProducts($userIdentifier);
                                        $availableProducts = $this->documentService->getAvailableProducts();
                                        $trackingProducts = array_intersect($userProducts, $availableProducts);

                                        foreach ($trackingProducts as $trackingProduct) {
                                            // Database Report 1 Regular Searches Tracking Identifier (dr1 = Database Report 1, RS = Regular Searches)
                                            $dr1SearchTrackingIdentifier = sprintf(
                                                    '%s:%s:%s',
                                                    'RS',
                                                    $userIdentifier,
                                                    $trackingProduct
                                            );

                                            // Tracks Regular Searches for Database Report 1 (dr1 = Database Report 1)
                                            $dr1SearchCVAR = sprintf(
                                                    'cvar={"%d":["%s","%s"]}',
                                                    self::DATABASE_REPORT_1_SLOT,
                                                    self::DATABASE_REPORT_1,
                                                    $dr1SearchTrackingIdentifier
                                            );
                                            $dr1SearchTrackingRequest = sprintf(
                                                    '?%s&%s&%s',
                                                    $dr1SearchCVAR,
                                                    $idsiteStr,
                                                    $recStr
                                            );
                                            $dr1SearchPromise = $this->httpClient->postAsync($dr1SearchTrackingRequest);
                                            $dr1SearchPromise->wait();
                                        }
                                    }

                                    // Platform Report 1 Regular Searches Tracking Identifier (pr1 = Platform Report 1, RS = Regular Searches)
                                    $pr1SearchTrackingIdentifier = sprintf('%s:%s', 'RS', $userIdentifier);

                                    // Tracks Regular Searches for Platform Report 1 (pr1 = Platform Report 1)
                                    $pr1SearchCVAR = sprintf(
                                            'cvar={"%d":["%s","%s"]}',
                                            self::PLATFORM_REPORT_1_SLOT,
                                            self::PLATFORM_REPORT_1,
                                            $pr1SearchTrackingIdentifier
                                    );
                                    $pr1SearchTrackingRequest = sprintf(
                                            '?%s&%s&%s',
                                            $pr1SearchCVAR,
                                            $idsiteStr,
                                            $recStr
                                    );
                                    $pr1SearchPromise = $this->httpClient->postAsync($pr1SearchTrackingRequest);
                                    $pr1SearchPromise->wait();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
