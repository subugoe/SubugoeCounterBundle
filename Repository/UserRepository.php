<?php

namespace Subugoe\CounterBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * UserRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class UserRepository extends EntityRepository
{
    /*
     * Returns the user's identifier
     *
     * @param string $clientIp The user's client IP
     *
     * @return string $identifier The user $identifier
     */
    public function getUserIdentifier($clientIp)
    {
        $query = $this->createQueryBuilder('u')
            ->select('u.identifier')
            ->setMaxResults(1)
            ->where(':clientIp between u.startIpAddress AND u.endIpAddress')
            ->setParameter('clientIp', $clientIp)
            ->getQuery();

        $identifier = $query->getOneOrNullResult();

        return $identifier;
    }

    /*
     * Returns all the users
     *
     * @return array $users The users
     *
     */
    public function getUsers()
    {
        $query = $this->createQueryBuilder('u')->getQuery();
        $users = $query->getResult();

        return $users;
    }

    /*
     * Returns user specific products
     *
     * @param string $userIdentifier The user identifier
     *
     * @return array $products The list of user's products
     */
    public function getUserProducts($userIdentifier)
    {
        $query = $this->createQueryBuilder('u')
                ->select('u.product')
                ->where('u.identifier = ?1')
                ->setParameter(1, $userIdentifier)
                ->getQuery();

        $products = $query->getResult();

        return $products;
    }
}
