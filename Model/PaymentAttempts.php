<?php

namespace NoFraud\Connect\Model;

/**
 * Tracks failed payment attempts on a quote within a rolling time window.
 *
 * Failure timestamps are stored as a JSON list so cardAttempts reflects recent
 * attempts only, not every failure over the lifetime of a long-lived cart.
 */
class PaymentAttempts
{
    public const WINDOW_SECONDS = 3600;
    public const MAX_TIMES = 100;
    public const COUNT_FIELD = 'nofraud_failed_payment_attempts';
    public const TIMES_FIELD = 'nofraud_failed_payment_times';

    /**
     * Quote IDs already counted during this request
     *
     * @var array
     */
    private $countedQuoteIds = [];

    /**
     * Record a failed payment attempt, at most once per quote per request
     *
     * @param \Magento\Framework\DataObject $quote
     * @param int|null $now
     * @return bool Whether the failure was counted
     */
    public function recordFailure($quote, ?int $now = null): bool
    {
        $quoteId = (int)$quote->getId();
        if (isset($this->countedQuoteIds[$quoteId])) {
            return false;
        }
        $this->countedQuoteIds[$quoteId] = true;

        $now = $now ?? time();
        $times = $this->getRecentTimes($quote, $now);
        $times[] = $now;
        $times = array_slice($times, -self::MAX_TIMES);

        $quote->setData(self::TIMES_FIELD, json_encode($times));
        // Kept in sync for visibility when inspecting carts; not read back
        $quote->setData(self::COUNT_FIELD, count($times));

        return true;
    }

    /**
     * Number of failed payment attempts within the window
     *
     * @param \Magento\Framework\DataObject $quote
     * @param int|null $now
     * @return int
     */
    public function getRecentFailures($quote, ?int $now = null): int
    {
        return count($this->getRecentTimes($quote, $now ?? time()));
    }

    /**
     * Clear tracked failures, e.g. once an order is placed
     *
     * @param \Magento\Framework\DataObject $quote
     * @return void
     */
    public function reset($quote): void
    {
        $quote->setData(self::TIMES_FIELD, null);
        $quote->setData(self::COUNT_FIELD, 0);
    }

    /**
     * Failure timestamps that fall within the window
     *
     * @param \Magento\Framework\DataObject $quote
     * @param int $now
     * @return int[]
     */
    private function getRecentTimes($quote, int $now): array
    {
        $times = json_decode((string)$quote->getData(self::TIMES_FIELD), true);
        if (!is_array($times)) {
            return [];
        }

        return array_values(array_filter($times, function ($time) use ($now) {
            return is_int($time) && $now - $time <= self::WINDOW_SECONDS;
        }));
    }
}
