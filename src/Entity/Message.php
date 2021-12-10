<?php

namespace App\Entity;

use JMS\Serializer\Annotation as Serializer;

/**
 * Class Message
 *
 * @Serializer\XmlRoot(name=Message::MESSAGE_ATTR)
 * @Serializer\XmlNamespace(uri="http://www.w3.org/2005/Atom", prefix="atom")
 */
class Message
{
    public const MESSAGE_ATTR = 'message';
    public const CODE_ATTR = 'code';

    /**
     * Code
     *
     * @Serializer\SerializedName(Message::CODE_ATTR)
     */
    private int $code;

    /**
     * Message
     *
     * @Serializer\SerializedName(Message::MESSAGE_ATTR)
     */
    private ?string $message;

    /**
     * Message constructor.
     *
     * @param int    $code    code
     * @param null|string $message message
     */
    public function __construct(int $code = 0, ?string $message = null)
    {
        $this->code = $code;
        $this->message = $message;
    }

    /**
     * Get code
     *
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Set code
     *
     * @param int $code code
     *
     * @return void
     */
    public function setCode(int $code): void
    {
        $this->code = $code;
    }

    /**
     * Get message
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Set message
     *
     * @param string|null $message message
     *
     * @return void
     */
    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }
}
