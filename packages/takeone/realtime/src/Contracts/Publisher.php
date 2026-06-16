<?php

namespace Takeone\Realtime\Contracts;

interface Publisher
{
    /**
     * Publish a JSON payload to a single MQTT topic.
     *
     * Implementations must honour the package's fail_silently setting: a broker
     * outage should never bubble up into the web request (the DB write is the
     * source of truth). Returns true if the message reached the broker.
     */
    public function publish(string $topic, array $payload): bool;

    /**
     * Publish many messages over a single broker connection.
     *
     * @param array<int, array{topic:string, payload:array}> $messages
     */
    public function publishMany(array $messages): bool;
}
