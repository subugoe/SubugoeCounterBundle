<?php

namespace Subugoe\CounterBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * User.
 *
 * @ORM\Table(name="user", indexes={@Index(name="search_idx", columns={"startIpAddress", "endIpAddress"})})
 * @ORM\Entity(repositoryClass="Subugoe\CounterBundle\Repository\UserRepository")
 */
class User
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="startIpAddress", type="bigint")
     */
    private $startIpAddress;

    /**
     * @var string
     *
     * @ORM\Column(name="endIpAddress", type="bigint")
     */
    private $endIpAddress;

    /**
     * @var string
     *
     * @ORM\Column(name="institution", type="string", length=255, nullable=true)
     */
    private $institution;

    /**
     * @var string
     *
     * @ORM\Column(name="product", type="string", length=30)
     */
    private $product;

    /**
     * @var string
     *
     * @ORM\Column(name="identifier", type="string", length=10)
     */
    private $identifier;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255)
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(name="zuid", type="string")
     */
    private $zuid;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set startIpAddress.
     *
     * @param string $startIpAddress
     *
     * @return User
     */
    public function setStartIpAddress($startIpAddress)
    {
        $this->startIpAddress = $startIpAddress;

        return $this;
    }

    /**
     * Get startIpAddress.
     *
     * @return string
     */
    public function getStartIpAddress()
    {
        return $this->startIpAddress;
    }

    /**
     * Set endIpAddress.
     *
     * @param string $endIpAddress
     *
     * @return User
     */
    public function setEndIpAddress($endIpAddress)
    {
        $this->endIpAddress = $endIpAddress;

        return $this;
    }

    /**
     * Get endIpAddress.
     *
     * @return string
     */
    public function getEndIpAddress()
    {
        return $this->endIpAddress;
    }

    /**
     * Set institution.
     *
     * @param string $institution
     *
     * @return User
     */
    public function setInstitution($institution)
    {
        $this->institution = $institution;

        return $this;
    }

    /**
     * Get institution.
     *
     * @return string
     */
    public function getInstitution()
    {
        return $this->institution;
    }

    /**
     * Set product.
     *
     * @param string $product
     *
     * @return User
     */
    public function setProduct($product)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get product.
     *
     * @return string
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set identifier.
     *
     * @param string $identifier
     *
     * @return User
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Get identifier.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Set email.
     *
     * @param string $email
     *
     * @return User
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set zuid.
     *
     * @param string $zuid
     *
     * @return User
     */
    public function setZuid($zuid)
    {
        $this->zuid = $zuid;

        return $this;
    }

    /**
     * Get zuid.
     *
     * @return string
     */
    public function getZuid()
    {
        return $this->zuid;
    }
}
