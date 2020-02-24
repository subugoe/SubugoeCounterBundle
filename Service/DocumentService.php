<?php

namespace Subugoe\CounterBundle\Service;

use Solarium\Client;
use Solarium\QueryType\Select\Result\DocumentInterface;

/**
 * Service for getting document data from solr index.
 */
class DocumentService
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * DocumentService constructor.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Returns the already indexed products from solr server.
     *
     * @return array $products The product list
     */
    public function getAvailableProducts(): array
    {
        $query = $this->client->createSelect();
        $query->setFields(['product']);
        $query->addParam('group', true);
        $query->addParam('group.field', 'product');
        $query->addParam('group.main', true);
        $resultset = $this->client->select($query)->getDocuments();

        return array_column($resultset, 'product');
    }

    /**
     * Returns the document data needed for tracking.
     */
    public function getDocument(string $searchStr, string $id, array $documentFields): DocumentInterface
    {
        $select = $this->client->createSelect();
        $select->setQuery($searchStr.':'.$id);
        $select->setFields($documentFields);
        $document = $this->client->select($select);
        $document = $document->getDocuments();

        return $document[0];
    }
}
