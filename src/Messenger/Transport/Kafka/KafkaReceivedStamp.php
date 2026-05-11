<?php

declare(strict_types=1);

namespace App\Messenger\Transport\Kafka;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Carries the original RdKafka\Message of a received envelope, so that
 * ack()/reject() can commit the correct offset back to Kafka.
 */
final readonly class KafkaReceivedStamp implements NonSendableStampInterface
{
    /**
     * @param object $message An instance of \RdKafka\Message (typed as object so the class can be
     *                        autoloaded even when the rdkafka extension is not installed at parse time).
     */
    public function __construct(
        public object $message,
    ) {
    }
}
