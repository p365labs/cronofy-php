<?php

declare(strict_types=1);

namespace Cronofy\Exception;

use Exception;

class CronofyException extends Exception
{
    /**
     * @var null
     */
    private $errorDetails;

    /**
     * CronofyException constructor.
     *
     * @param string $message
     * @param int    $code
     * @param null   $errorDetails
     */
    public function __construct(string $message, int $code = 0, $errorDetails = null)
    {
        $this->errorDetails = $errorDetails;

        parent::__construct($message, $code, null);
    }

    public function error_details(): ?array
    {
        return $this->errorDetails;
    }
}
