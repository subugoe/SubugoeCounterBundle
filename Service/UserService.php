<?php

namespace Subugoe\CounterBundle\Service;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for getting user data from database.
 */
class UserService
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
     * ReportService constructor.
     *
     * @param RegistryInterface $doctrine
     * @param RequestStack      $request
     */
    public function __construct(RegistryInterface $doctrine, RequestStack $request)
    {
        $this->doctrine = $doctrine;
        $this->request = $request;
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

    /**
     * Returns the list of user products.
     *
     * @param string $identifier The user identifier
     *
     * @return array $userProducts The user products
     */
    public function getUserProducts($identifier)
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
    public function getUsers()
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
}
