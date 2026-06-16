<?php

namespace Takeone\Realtime\Mqtt;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;
use Takeone\Realtime\Contracts\Publisher;
use Takeone\Realtime\RealtimeManager;

/**
 * Publishes to the broker over plain TCP using php-mqtt. A fresh short-lived
 * connection is opened per publish call: web requests are infrequent relative
 * to the broker and this keeps the publisher stateless and worker-safe (no
 * dangling sockets across queued jobs). For high-volume fan-out, batch through
 * publishMany() so the connection is reused.
 */
class PhpMqttPublisher implements Publisher
{
    public function __construct(private RealtimeManager $manager) {}

    public function publish(string $topic, array $payload): bool
    {
        return $this->withClient(function (MqttClient $client) use ($topic, $payload) {
            $client->publish(
                $topic,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $this->manager->config('qos', 1),
                $this->manager->config('retain', false),
            );
            $this->flush($client);
        });
    }

    /** @param array<int, array{topic:string, payload:array}> $messages */
    public function publishMany(array $messages): bool
    {
        return $this->withClient(function (MqttClient $client) use ($messages) {
            foreach ($messages as $m) {
                $client->publish(
                    $m['topic'],
                    json_encode($m['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $this->manager->config('qos', 1),
                    $this->manager->config('retain', false),
                );
            }
            $this->flush($client);
        });
    }

    /**
     * Pump the client loop until the outgoing queues drain (QoS>0 PUBACKs
     * received) before we disconnect. Without this the publisher would close
     * the socket the instant after writing — fine for tiny text frames, but a
     * large payload (e.g. an image) could be cut off mid-flight and silently
     * dropped. Bounded so a missing ack can never hang a web request.
     */
    private function flush(MqttClient $client): void
    {
        if ((int) $this->manager->config('qos', 1) < 1) {
            return; // QoS 0 has no ack; the write already completed.
        }
        $client->loop(false, true, (int) $this->manager->config('flush_timeout', 3));
    }

    private function withClient(callable $fn): bool
    {
        try {
            $client = new MqttClient(
                $this->manager->config('broker.host', '127.0.0.1'),
                (int) $this->manager->config('broker.port', 1883),
                $this->manager->config('broker.client_id', 'takeone-laravel') . '-' . getmypid(),
            );

            // Authenticate the publisher. If no static password is configured we
            // mint a short-lived server JWT (publish-everywhere ACL) so the broker
            // can run a single JWT authenticator with no DB accounts.
            $username = $this->manager->config('broker.username');
            $password = $this->manager->config('broker.password');
            if (! $password) {
                $server   = app(\Takeone\Realtime\Mqtt\TokenIssuer::class)->issueServer();
                $username = $server['username'];
                $password = $server['token'];
            }

            // ConnectionSettings is IMMUTABLE — every setter returns a clone, so
            // the chain result MUST be reassigned or the credentials are lost.
            $settings = (new ConnectionSettings)
                ->setKeepAliveInterval(10)
                ->setConnectTimeout(3)
                ->setSocketTimeout(3)
                ->setUsername($username ?: null)
                ->setPassword($password ?: null);

            $client->connect($settings, true);
            $fn($client);
            $client->disconnect();

            return true;
        } catch (\Throwable $e) {
            if (! $this->manager->config('fail_silently', true)) {
                throw $e;
            }
            Log::warning('[realtime] MQTT publish failed: ' . $e->getMessage());

            return false;
        }
    }
}
