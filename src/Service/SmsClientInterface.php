<?php

namespace App\Service;

use App\Exception\SmsException;

interface SmsClientInterface
{
    /**
     * Send SMS.
     *
     * @param array $to
     *   Array of phone numbers to send SMS to
     * @param string $message
     *   The message to send
     * @param bool $flash
     *   If true, a flash message is sent if support else converted to regular
     *   sms (default: false)
     *
     * @return int
     *   The batch number from the gateway or -1 on dry-runs
     *
     * @throws SmsException
     * @throws \DateMalformedStringException
     */
    public function send(array $to, string $message, bool $flash = false): int;
}
