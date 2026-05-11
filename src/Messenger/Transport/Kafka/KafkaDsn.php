<?php

declare(strict_types=1);

namespace App\Messenger\Transport\Kafka;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * Parses a Kafka DSN of the form:
 *   kafka://host1:9092,host2:9092/?topic=messages&group_id=blog&auto_offset_reset=earliest&commit_async=0
 *
 * Multiple bootstrap brokers can be passed comma-separated in the host part.
 */
final readonly class KafkaDsn
{
    /**
     * @param list<string> $brokers
     * @param array<string, string> $consumerConfig
     * @param array<string, string> $producerConfig
     */
    public function __construct(
        public string $topic,
        public string $groupId,
        public array $brokers,
        public int $consumeTimeoutMs,
        public int $flushTimeoutMs,
        public bool $commitAsync,
        public array $consumerConfig,
        public array $producerConfig,
    ) {
    }

    public static function fromString(string $dsn, array $extraOptions = []): self
    {
        if (!str_starts_with($dsn, 'kafka://')) {
            throw new InvalidArgumentException(\sprintf('Kafka DSN must start with "kafka://" (got "%s").', $dsn));
        }

        $parsed = parse_url($dsn);
        if (false === $parsed || !isset($parsed['host'])) {
            throw new InvalidArgumentException(\sprintf('Invalid Kafka DSN: "%s".', $dsn));
        }

        $hostPart = $parsed['host'];
        if (isset($parsed['port'])) {
            $hostPart .= ':'.$parsed['port'];
        }

        $brokers = array_values(array_filter(array_map(
            static fn (string $b): string => trim($b),
            explode(',', $hostPart),
        ), static fn (string $b): bool => '' !== $b));

        if ([] === $brokers) {
            throw new InvalidArgumentException('Kafka DSN must contain at least one broker host.');
        }

        $query = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }
        $query = array_merge($query, $extraOptions);

        $topic = (string) ($query['topic'] ?? 'messages');
        $groupId = (string) ($query['group_id'] ?? 'symfony-messenger');
        $consumeTimeoutMs = (int) ($query['consume_timeout_ms'] ?? 10000);
        $flushTimeoutMs = (int) ($query['flush_timeout_ms'] ?? 10000);
        $commitAsync = (bool) ($query['commit_async'] ?? false);

        $consumerConfig = self::extractConfig($query, 'consumer_');
        $producerConfig = self::extractConfig($query, 'producer_');

        if (isset($query['auto_offset_reset'])) {
            $consumerConfig['auto.offset.reset'] = (string) $query['auto_offset_reset'];
        }

        return new self(
            topic: $topic,
            groupId: $groupId,
            brokers: $brokers,
            consumeTimeoutMs: $consumeTimeoutMs,
            flushTimeoutMs: $flushTimeoutMs,
            commitAsync: $commitAsync,
            consumerConfig: $consumerConfig,
            producerConfig: $producerConfig,
        );
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, string>
     */
    private static function extractConfig(array $query, string $prefix): array
    {
        $out = [];
        foreach ($query as $key => $value) {
            if (\is_string($key) && str_starts_with($key, $prefix)) {
                $rdkafkaKey = str_replace('_', '.', substr($key, \strlen($prefix)));
                $out[$rdkafkaKey] = (string) $value;
            }
        }

        return $out;
    }
}
