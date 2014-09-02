<?php

namespace Pumukit\SchemaBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Pumukit\SchemaBundle\Document\Person
 *
 * @MongoDB\EmbeddedDocument
 */
class Person
{
	/**
	 * @var int $id
	 *
	 * @MongoDB\Id
	 */
	protected $id;

	/**
	 * @var string $name
	 *
	 * @MongoDB\String
	 */
	protected $name;

        /**
	 * @var string $email
         *
	 * @MongoDB\String
         */
        protected $email;

	/**
	 * @var string $web
	 *
	 * @MongoDB\String
	 * //@Assert\Url()
	 */
	protected $web;

	/**
	 * @var string $phone
	 *
	 * @MongoDB\String
	 */
	protected $phone;

	/**
	 * @var string $honorific
	 *
	 * @MongoDB\String
	 */
	protected $honorific;

	/**
	 * @var string $firm
	 *
	 * @MongoDB\String
	 */
	protected $firm;

	/**
	 * @var string $post
	 *
	 * @MongoDB\String
	 */
	protected $post;

	/**
	 * @var string $bio
	 *
	 * @MongoDB\String
	 */
	protected $bio;

	/**
	 * Locale
	 */
	protected $locale;

	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 */
	public function setName($name)
	{
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

        /**
         * Set email
         *
         * @param string $email
         */
        public function setEmail($email)
        {
        	$this->email = $email;
        }

        /**
	 * Get email
	 *
	 * @return string
	 */
        public function getEmail()
	{
		return $this->email;
	} 

	/**
	 * Set web
	 *
	 * @param string $web
	 */
	public function setWeb($web)
	{
		$this->web = $web;
	}

	/**
	 * Get web
	 *
	 * @return string
	 */
	public function getWeb()
	{
		return $this->web;
	}

	/**
	 * Set phone
	 *
	 * @param string $phone
	 */
	public function setPhone($phone)
	{
		$this->phone = $phone;
	}

	/**
	 * Get phone
	 *
	 * @return string
	 */
	public function getPhone()
	{
		return $this->phone;
	}

	/**
	 * Set honorific
	 *
	 * @param string $honorific
	 */
	public function setHonorific($honorific)
	{
		$this->honorific = $honorific;
	}

	/**
	 * Get honorific
	 *
	 * @return string
	 */
	public function getHonorific()
	{
		return $this->honorific;
	}

	/**
	 * Set firm
	 *
	 * @param string $firm
	 */
	public function setFirm($firm)
	{
		$this->firm = $firm;
	}

	/**
	 * Get firm
	 *
	 * @return string
	 */
	public function getFirm()
	{
		return $this->firm;
	}

	/**
	 * Set post
	 *
	 * @param string $post
	 */
	public function setPost($post)
	{
		$this->post = $post;
	}

	/**
	 * Get post
	 *
	 * @return string
	 */
	public function getPost()
	{
		return $this->post;
	}

	/**
	 * Set bio
	 *
	 * @param string $bio
	 */
	public function setBio($bio)
	{
		$this->bio = $bio;
	}

	/**
	 * Get bio
	 *
	 * @return string
	 */
	public function getBio()
	{
		return $this->bio;
	}
}
