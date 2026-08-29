import 'dart:async';
import 'dart:convert';

import 'package:mqtt_client/mqtt_client.dart';
import 'package:mqtt_client/mqtt_server_client.dart';

/// The instruction channel: what the mat tells this camera to do.
///
/// The whole feature rests on this arriving. Polling would mean a camera
/// starting several seconds after hajime, and on a ninety-second bout the first
/// exchange is the one people re-watch. So the phone holds a socket open and
/// starts within a frame or two of the scoring table's press.
///
/// Subscribe-only, one topic, credentials handed out by the server per camera
/// (see CameraChannel). The phone cannot publish anything anywhere, which is
/// the point: a camera on a tripod in a public hall must be worth nothing to
/// whoever picks it up.
///
/// It is a convenience, never a dependency. Every command that arrives here
/// also shows up in the config poll, so a broker outage makes the camera late,
/// not broken.
class CameraLink {
  CameraLink({required this.onCommand});

  /// Called with each decoded command: {action: record|stop|standby|paired…}.
  final void Function(Map<String, dynamic> command) onCommand;

  MqttServerClient? _client;
  StreamSubscription? _updates;
  String? _topic;

  bool get connected =>
      _client?.connectionStatus?.state == MqttConnectionState.connected;

  /// Point this link at the credentials the server just handed us.
  ///
  /// Reconnecting on every config poll would drop the socket every few seconds,
  /// so a call naming the same topic while already connected does nothing.
  Future<void> attach(Map<String, dynamic>? realtime) async {
    if (realtime == null) {
      await close();
      return;
    }

    final topic = realtime['topic'] as String?;
    final wsUrl = realtime['ws_url'] as String?;
    final username = realtime['username'] as String?;
    final password = realtime['password'] as String?;

    if (topic == null || wsUrl == null) return;
    if (connected && _topic == topic) return;

    await close();
    _topic = topic;

    // A stable per-topic id. Two cameras must never collide on a client id —
    // the broker would kick each in turn and neither would ever hear hajime.
    final client = MqttServerClient.withPort(wsUrl, 'cam-${topic.hashCode.toUnsigned(32)}', 443)
      ..useWebSocket = true
      ..websocketProtocols = MqttClientConstants.protocolsSingleDefault
      ..keepAlivePeriod = 30
      ..autoReconnect = true
      ..resubscribeOnAutoReconnect = true
      ..setProtocolV311()
      ..logging(on: false);

    client.connectionMessage = MqttConnectMessage()
        .withClientIdentifier('cam-${topic.hashCode.toUnsigned(32)}')
        .startClean()
        .authenticateAs(username, password);

    _client = client;

    try {
      await client.connect();
    } catch (_) {
      // Silent by design: the config poll is the backstop and the UI already
      // shows whether the link is up. A thrown error here would take the
      // camera down for a network blip.
      await close();
      return;
    }

    if (client.connectionStatus?.state != MqttConnectionState.connected) {
      await close();
      return;
    }

    client.subscribe(topic, MqttQos.atLeastOnce);

    _updates = client.updates?.listen((events) {
      for (final event in events) {
        final message = event.payload;
        if (message is! MqttPublishMessage) continue;

        try {
          final raw = MqttPublishPayload.bytesToStringAsString(message.payload.message);
          final decoded = jsonDecode(raw);
          if (decoded is Map<String, dynamic>) onCommand(decoded);
        } catch (_) {
          // A malformed frame is not worth a crash beside a live mat.
        }
      }
    });
  }

  Future<void> close() async {
    await _updates?.cancel();
    _updates = null;
    _topic = null;

    try {
      _client?.disconnect();
    } catch (_) {}

    _client = null;
  }
}
