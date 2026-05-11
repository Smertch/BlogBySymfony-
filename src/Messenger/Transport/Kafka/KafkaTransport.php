<?php

declare(strict_types=1);

namespace App\Messenger\Transport\Kafka;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Minimal Symfony Messenger transport backed by Kafka (via the rdkafka PHP extension).
 *
 * Send  -> produce to a single topic, blocking flush.
 * Get   -> consume one message at a time (returns 0 or 1 envelope per call).
 * Ack   -> commit the offset of the original message.
 * Reject-> commit the offset as well, so we don't reconsume; for real DLQ behaviour
 *          the consumer worker should route failed envelopes to a separate topic.
 */
final class KafkaTransport implements TransportInterface
{
    private ?object $consumer = null;
    private ?object $producer = null;
    private ?object $producerTopic = null;

    public function __construct(
        private readonly KafkaDsn $dsn,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function send(\Symfony\Component\Messenger\Envelope $envelope): \Symfony\Component\Messenger\Envelope
    {
        $this->ensureRdKafkaExtension();

        $encoded = $this->serializer->encode($envelope);
        $headers = [];
        foreach ((array) ($encoded['headers'] ?? []) as $name => $value) {
            $headers[(string) $name] = (string) $value;
        }

        $producer = $this->getProducer();
        $topic = $this->getProducerTopic();

        $topic->producev(
            \RD_KAFKA_PARTITION_UA,
            \RD_KAFKA_MSG_F_BLOCK,
            (string) $encoded['body'],
            null,
            $headers,
        );
        $producer->poll(0);

        $result = $producer->flush($this->dsn->flushTimeoutMs);
        if ($result !== \RD_KAFKA_RESP_ERR_NO_ERROR) {
            throw new TransportException(\sprintf('Kafka producer flush failed (rdkafka error code %d).', $result));
        }

        return $envelope;
    }

    public function get(): iterable
    {
        $this->ensureRdKafkaExtension();

        $consumer = $this->getConsumer();
        $message = $consumer->consume($this->dsn->consumeTimeoutMs);

        switch ($message->err) {
            case \RD_KAFKA_RESP_ERR_NO_ERROR:
                $headers = [];
                foreach ((array) ($message->headers ?? []) as $k => $v) {
                    $headers[(string) $k] = (string) $v;
                }

                $envelope = $this->serializer->decode([
                    'body' => (string) $message->payload,
                    'headers' => $headers,
                ]);

                return [$envelope->with(new KafkaReceivedStamp($message))];

            case \RD_KAFKA_RESP_ERR__PARTITION_EOF:
            case \RD_KAFKA_RESP_ERR__TIMED_OUT:
            case \RD_KAFKA_RESP_ERR__TRANSPORT:
                return [];

            default:
                throw new TransportException(\sprintf('Kafka consume failed: %s (%d).', $message->errstr(), $message->err));
        }
    }

    public function ack(\Symfony\Component\Messenger\Envelope $envelope): void
    {
        $stamp = $envelope->last(KafkaReceivedStamp::class);
        if (!$stamp instanceof KafkaReceivedStamp) {
            return;
        }

        $consumer = $this->getConsumer();
        if ($this->dsn->commitAsync) {
            $consumer->commitAsync($stamp->message);
        } else {
            $consumer->commit($stamp->message);
        }
    }

    public function reject(\Symfony\Component\Messenger\Envelope $envelope): void
    {
        // Commit the offset so the message isn't replayed forever.
        // Wire a DLQ topic via Messenger's `failure_transport` for permanent failures.
        $this->ack($envelope);
    }

    private function getProducer(): object
    {
        if ($this->producer !== null) {
            return $this->producer;
        }

        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', implode(',', $this->dsn->brokers));
        $conf->set('socket.timeout.ms', '50');
        $conf->set('queue.buffering.max.ms', '0');
        foreach ($this->dsn->producerConfig as $key => $value) {
            $conf->set($key, $value);
        }

        return $this->producer = new \RdKafka\Producer($conf);
    }

    private function getProducerTopic(): object
    {
        return $this->producerTopic ??= $this->getProducer()->newTopic($this->dsn->topic);
    }

    private function getConsumer(): object
    {
        if ($this->consumer !== null) {
            return $this->consumer;
        }

        $conf = new \RdKafka\Conf();
        $conf->set('group.id', $this->dsn->groupId);
        $conf->set('metadata.broker.list', implode(',', $this->dsn->brokers));
        $conf->set('enable.auto.commit', 'false');
        $conf->set('auto.offset.reset', 'earliest');
        foreach ($this->dsn->consumerConfig as $key => $value) {
            $conf->set($key, $value);
        }

        $consumer = new \RdKafka\KafkaConsumer($conf);
        $consumer->subscribe([$this->dsn->topic]);

        return $this->consumer = $consumer;
    }

    private function ensureRdKafkaExtension(): void
    {
        if (!\extension_loaded('rdkafka')) {
            throw new LogicException('The "rdkafka" PHP extension is required for the Kafka Messenger transport. Install it with PECL (`pecl install rdkafka`) or use the provided Docker image.');
        }
    }
}
