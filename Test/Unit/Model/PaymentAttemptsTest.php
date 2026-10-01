<?php

namespace NoFraud\Connect\Test\Unit\Model;

use Magento\Framework\DataObject;
use NoFraud\Connect\Model\PaymentAttempts;
use PHPUnit\Framework\TestCase;

class PaymentAttemptsTest extends TestCase
{
    private const NOW = 1790000000;

    /** @var PaymentAttempts */
    private $attempts;

    protected function setUp(): void
    {
        $this->attempts = new PaymentAttempts();
    }

    private function quote(array $data = []): DataObject
    {
        return new DataObject(array_merge(['id' => 42], $data));
    }

    public function testFreshQuoteHasNoRecentFailures(): void
    {
        $this->assertSame(0, $this->attempts->getRecentFailures($this->quote(), self::NOW));
    }

    public function testRecordFailureCountsOnce(): void
    {
        $quote = $this->quote();

        $this->attempts->recordFailure($quote, self::NOW);

        $this->assertSame(1, $this->attempts->getRecentFailures($quote, self::NOW));
        $this->assertSame(1, $quote->getData(PaymentAttempts::COUNT_FIELD));
    }

    public function testSameQuoteIsOnlyCountedOncePerRequest(): void
    {
        // Legacy onepage SaveOrder calls PaymentFailuresService::handle() twice for one gateway failure
        $quote = $this->quote();

        $this->attempts->recordFailure($quote, self::NOW);
        $this->attempts->recordFailure($quote, self::NOW);

        $this->assertSame(1, $this->attempts->getRecentFailures($quote, self::NOW));
    }

    public function testFailuresAcrossRequestsAccumulateWithinWindow(): void
    {
        $quote = $this->quote();

        (new PaymentAttempts())->recordFailure($quote, self::NOW - 1800);
        (new PaymentAttempts())->recordFailure($quote, self::NOW - 600);
        (new PaymentAttempts())->recordFailure($quote, self::NOW);

        $this->assertSame(3, $this->attempts->getRecentFailures($quote, self::NOW));
    }

    public function testFailuresOlderThanWindowAreNotCounted(): void
    {
        $quote = $this->quote();

        (new PaymentAttempts())->recordFailure($quote, self::NOW - PaymentAttempts::WINDOW_SECONDS - 1);
        (new PaymentAttempts())->recordFailure($quote, self::NOW - 60);

        $this->assertSame(1, $this->attempts->getRecentFailures($quote, self::NOW));
    }

    public function testSlowTrickleOfFailuresDoesNotKeepExtendingWindow(): void
    {
        $quote = $this->quote();

        for ($i = 12; $i >= 0; $i--) {
            (new PaymentAttempts())->recordFailure($quote, self::NOW - ($i * 50 * 60));
        }

        // Only failures at -50m and now fall inside the last hour
        $this->assertSame(2, $this->attempts->getRecentFailures($quote, self::NOW));
    }

    public function testLegacyCounterWithoutTimestampsIsIgnored(): void
    {
        // Carts carrying a 1.7.0 counter must not inflate cardAttempts after upgrade
        $quote = $this->quote([PaymentAttempts::COUNT_FIELD => '12']);

        $this->assertSame(0, $this->attempts->getRecentFailures($quote, self::NOW));

        $this->attempts->recordFailure($quote, self::NOW);

        $this->assertSame(1, $this->attempts->getRecentFailures($quote, self::NOW));
    }

    public function testMalformedTimestampsAreIgnored(): void
    {
        $quote = $this->quote([PaymentAttempts::TIMES_FIELD => 'not json']);

        $this->assertSame(0, $this->attempts->getRecentFailures($quote, self::NOW));
    }

    public function testStoredTimestampsAreBounded(): void
    {
        $quote = $this->quote();

        for ($i = 0; $i < PaymentAttempts::MAX_TIMES + 20; $i++) {
            (new PaymentAttempts())->recordFailure($quote, self::NOW);
        }

        $this->assertSame(PaymentAttempts::MAX_TIMES, $this->attempts->getRecentFailures($quote, self::NOW));
    }

    public function testResetClearsCounter(): void
    {
        $quote = $this->quote();
        $this->attempts->recordFailure($quote, self::NOW);

        $this->attempts->reset($quote);

        $this->assertSame(0, $this->attempts->getRecentFailures($quote, self::NOW));
        $this->assertSame(0, $quote->getData(PaymentAttempts::COUNT_FIELD));
    }
}
