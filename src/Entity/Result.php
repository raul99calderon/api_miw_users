<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation as Serializer;
use JsonSerializable;

/**
 * @ORM\Entity
 *
 * @Serializer\XmlNamespace(uri="http://www.w3.org/2005/Atom", prefix="atom")
 * @Serializer\AccessorOrder(
 *     "custom",
 *     custom={ "id", "result", "user", "date" }
 *     )
 *
 * @Hateoas\Relation(
 *     name="parent",
 *     href="expr(constant('\\App\\Controller\\ApiResultsController::RUTA_API'))"
 * )
 *
 * @Hateoas\Relation(
 *     name="self",
 *     href="expr(constant('\\App\\Controller\\ApiResultsController::RUTA_API') ~ '/' ~ object.getId())"
 * )
 */
class Result implements JsonSerializable
{
    public const RESULT_ATTR = 'result';
    public const USER_ATTR = 'user';
    public const DATE_ATTR = 'date';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     *
     * @Serializer\XmlAttribute
     */
    private ?int $id = 0;

    /**
     * @ORM\Column(type="integer")
     *
     * @Serializer\SerializedName(Result::RESULT_ATTR)
     * @Serializer\XmlElement(cdata=false)
     */
    private int $result;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumns({
     *     @ORM\JoinColumn(
     *          name                 = "user_id",
     *          referencedColumnName = "id",
     *          onDelete             = "cascade"
     *     )
     * })
     *
     * @Serializer\SerializedName(Result::USER_ATTR)
     *
     * @var User $user
     */
    private User $user;

    /**
     * @var DateTime Date
     * @ORM\Column(
     *     type="datetime",
     *     nullable=false
     * )
     * @Serializer\SerializedName(Result::DATE_ATTR)
     */
    private DateTime $date;

    /**
     * Result constructor.
     *
     * @param int $result result
     * @param User|null $user user
     * @param DateTime|null $time time
     */
    public function __construct(int $result = 0, ?User $user = null, ?DateTime $time = null)
    {
        $this->result = $result;
        $this->user = $user;
        $this->date = $time;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getResult(): int
    {
        return $this->result;
    }

    /**
     * @param int $result
     * @return $this
     */
    public function setResult(int $result): self
    {
        $this->result = $result;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    /**
     * @return string|null
     */
    public function getFormattedDate(): ?string
    {
        return $this->getDate()->format('Y-m-d H:i:s');
    }

    /**
     * @param DateTime|null $date
     * @return $this
     */
    public function setDate(?DateTime $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getUserIdentifier() {
        return $this->user->getUserIdentifier();
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return array(
            'id'     => $this->id,
            self::RESULT_ATTR => $this->result,
            self::USER_ATTR   => $this->user,
            self::DATE_ATTR   => $this->date->format('Y-m-d H:i:s')
        );
    }
}
