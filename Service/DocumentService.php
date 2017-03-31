<?php

namespace Subugoe\CounterBundle\Service;

use Solarium\Client;

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
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
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
}
