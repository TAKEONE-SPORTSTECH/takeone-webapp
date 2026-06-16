<?php

namespace Takeone\Realtime\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Takeone\Realtime\Models\RealtimeSetting;
use Takeone\Realtime\RealtimeManager;

class RealtimePluginController extends Controller
{
    public function __construct(private RealtimeManager $realtime) {}

    /** Plugin management screen in the platform admin. */
    public function index()
    {
        return view('realtime::admin.index', [
            'layout'   => config('realtime.admin.layout'),
            'settings' => $this->currentSettings(),
            'status'   => $this->probe(),
        ]);
    }

    /** Persist broker settings + master switch from the form. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled'         => 'sometimes|boolean',
            'broker_host'     => 'required|string|max:255',
            'broker_port'     => 'required|integer|min:1|max:65535',
            'broker_username' => 'nullable|string|max:255',
            'broker_password' => 'nullable|string|max:255',
            'broker_ws_url'   => 'required|string|max:255',
            'jwt_secret'      => 'nullable|string|max:255',
            'jwt_ttl'         => 'required|integer|min:60|max:86400',
        ]);

        RealtimeSetting::putMany([
            'enabled'         => $request->boolean('enabled') ? '1' : '0',
            'broker.host'     => $data['broker_host'],
            'broker.port'     => (string) $data['broker_port'],
            'broker.username' => $data['broker_username'] ?? '',
            'broker.ws_url'   => $data['broker_ws_url'],
            'jwt.ttl'         => (string) $data['jwt_ttl'],
        ] + (filled($data['broker_password'] ?? null) ? ['broker.password' => $data['broker_password']] : [])
          + (filled($data['jwt_secret'] ?? null) ? ['jwt.secret' => $data['jwt_secret']] : []));

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Realtime settings saved.',
                'status'   => $this->probe(),
                'settings' => $this->currentSettings(),
            ]);
        }

        return back()->with('success', 'Realtime settings saved.');
    }

    /** Fire a test message at the broker and report the round-trip result. */
    public function test(Request $request)
    {
        $ok = false;
        $error = null;

        try {
            // fail_silently is bypassed here so we surface the real reason.
            config(['realtime.fail_silently' => false]);
            $ok = $this->realtime->publisher()->publish(
                config('realtime.prefix', 'takeone') . '/_diagnostics/ping',
                ['ping' => true, 'at' => now()->toIso8601String()],
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Broker reachable — test message published.' : ('Broker unreachable: ' . $error),
        ], $ok ? 200 : 422);
    }

    private function currentSettings(): array
    {
        return [
            'enabled'         => $this->realtime->enabled(),
            'broker_host'     => $this->realtime->config('broker.host'),
            'broker_port'     => $this->realtime->config('broker.port'),
            'broker_username' => $this->realtime->config('broker.username'),
            'broker_ws_url'   => $this->realtime->config('broker.ws_url'),
            'jwt_ttl'         => $this->realtime->config('jwt.ttl'),
            'jwt_secret_set'  => filled($this->realtime->config('jwt.secret')),
        ];
    }

    /** Lightweight reachability probe for the status pill (TCP connect only). */
    private function probe(): array
    {
        if (! $this->realtime->enabled()) {
            return ['state' => 'disabled', 'label' => 'Disabled'];
        }

        $host = $this->realtime->config('broker.host', '127.0.0.1');
        $port = (int) $this->realtime->config('broker.port', 1883);
        $conn = @fsockopen($host, $port, $errno, $errstr, 2);

        if ($conn) {
            fclose($conn);
            return ['state' => 'online', 'label' => 'Broker online'];
        }

        return ['state' => 'offline', 'label' => 'Broker offline (' . ($errstr ?: 'no route') . ')'];
    }
}
