<?php

namespace Adadgio\ParseBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass()
 * ORM\Table()
 * ORM\Entity(repositoryClass="Adadgio\ParseBundle\Repository\ParseInstallationRepository")
 */
class ParseInstallation
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string", unique=true)
     */
    private $installationId;

    /**
     * @ORM\Column(type="string")
     */
    private $appIdentifier;

    /**
     * @ORM\Column(type="string")
     */
    private $appName;

    /**
     * @ORM\Column(type="string")
     */
    private $appVersion;

    /**
     * @ORM\Column(type="string")
     */
    private $deviceType;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $deviceToken;

    /**
     * @ORM\Column(type="string")
     */
    private $localeIdentifier;

    /**
     * @ORM\Column(type="string")
     */
    private $timeZone;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $pushType;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $channels;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $badge;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $GCMSenderId;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $installationDate;

    /**
     * ORM\ManyToOne(targetEntity="AppBundle\Entity\User", inversedBy="installations")
     * ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true)
     */
    //protected $user;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->installationDate = new \DateTime();
    }

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
     * Set installationId
     *
     * @param string $installationId
     * @return Installation
     */
    public function setInstallationId($installationId)
    {
        $this->installationId = $installationId;

        return $this;
    }

    /**
     * Get installationId
     *
     * @return string
     */
    public function getInstallationId()
    {
        return $this->installationId;
    }

    /**
     * Set appIdentifier
     *
     * @param string $appIdentifier
     * @return Installation
     */
    public function setAppIdentifier($appIdentifier)
    {
        $this->appIdentifier = $appIdentifier;

        return $this;
    }

    /**
     * Get appIdentifier
     *
     * @return string
     */
    public function getAppIdentifier()
    {
        return $this->appIdentifier;
    }

    /**
     * Set appName
     *
     * @param string $appName
     * @return Installation
     */
    public function setAppName($appName)
    {
        $this->appName = $appName;

        return $this;
    }

    /**
     * Get appName
     *
     * @return string
     */
    public function getAppName()
    {
        return $this->appName;
    }

    /**
     * Set appVersion
     *
     * @param string $appVersion
     * @return Installation
     */
    public function setAppVersion($appVersion)
    {
        $this->appVersion = $appVersion;

        return $this;
    }

    /**
     * Get appVersion
     *
     * @return string
     */
    public function getAppVersion()
    {
        return $this->appVersion;
    }

    /**
     * Set deviceType
     *
     * @param string $deviceType
     * @return Installation
     */
    public function setDeviceType($deviceType)
    {
        $this->deviceType = $deviceType;

        return $this;
    }

    /**
     * Get deviceType
     *
     * @return string
     */
    public function getDeviceType()
    {
        return $this->deviceType;
    }

    /**
     * Set deviceToken
     *
     * @param string $deviceToken
     * @return Installation
     */
    public function setDeviceToken($deviceToken)
    {
        $this->deviceToken = $deviceToken;

        return $this;
    }

    /**
     * Get deviceToken
     *
     * @return string
     */
    public function getDeviceToken()
    {
        return $this->deviceToken;
    }

    /**
     * Set localeIdentifier
     *
     * @param string $localeIdentifier
     * @return Installation
     */
    public function setLocaleIdentifier($localeIdentifier)
    {
        $this->localeIdentifier = $localeIdentifier;

        return $this;
    }

    /**
     * Get localeIdentifier
     *
     * @return string
     */
    public function getLocaleIdentifier()
    {
        return $this->localeIdentifier;
    }

    /**
     * Set timeZone
     *
     * @param string $timeZone
     * @return Installation
     */
    public function setTimeZone($timeZone)
    {
        $this->timeZone = $timeZone;

        return $this;
    }

    /**
     * Get timeZone
     *
     * @return string
     */
    public function getTimeZone()
    {
        return $this->timeZone;
    }

    /**
     * Set pushType
     *
     * @param string $pushType
     * @return Installation
     */
    public function setPushType($pushType)
    {
        $this->pushType = $pushType;

        return $this;
    }

    /**
     * Get pushType
     *
     * @return string
     */
    public function getPushType()
    {
        return $this->pushType;
    }

    /**
     * Set channels
     *
     * @param string $channels
     * @return Installation
     */
    public function setChannels($channels)
    {
        $this->channels = $channels;

        return $this;
    }

    /**
     * Get channels
     *
     * @return string
     */
    public function getChannels()
    {
        return $this->channels;
    }

    /**
     * Set badge
     *
     * @param string $badge
     * @return Installation
     */
    public function setBadge($badge)
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * Get badge
     *
     * @return string
     */
    public function getBadge()
    {
        return $this->badge;
    }

    /**
     * Set GCMSenderId
     *
     * @param string $gCMSenderId
     * @return Installation
     */
    public function setGCMSenderId($gCMSenderId)
    {
        $this->GCMSenderId = $gCMSenderId;

        return $this;
    }

    /**
     * Get GCMSenderId
     *
     * @return string
     */
    public function getGCMSenderId()
    {
        return $this->GCMSenderId;
    }

    /**
     * Set installationDate
     *
     * @param \DateTime $installationDate
     * @return Installation
     */
    public function setInstallationDate($installationDate)
    {
        $this->installationDate = $installationDate;

        return $this;
    }

    /**
     * Get installationDate
     *
     * @return \DateTime
     */
    public function getInstallationDate()
    {
        return $this->installationDate;
    }
}
