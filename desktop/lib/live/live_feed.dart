import 'dart:async';

import 'package:clock/clock.dart';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/models.dart';
import '../auth/session.dart';
import '../core/api_client.dart';

/// Avisos de tiempo real del servidor (evento DietsChanged del canal privado «diets»): solo
/// los tipos de cambio (diet, dispatch, day) de la empresa activa, sin datos clínicos. Cada
/// pantalla vuelve a consultar lo suyo al recibirlos (como resources/js/live.js en la web).
abstract class LiveFeed {
  Stream<Set<String>> get events;

  /// true con el WebSocket conectado; si no, las pantallas consultan cada pocos segundos
  ValueListenable<bool> get connected;

  void dispose();
}

/// Sin WebSocket configurado en el servidor: todo funciona con la consulta periódica
class NoLiveFeed implements LiveFeed {
  @override
  Stream<Set<String>> get events => const Stream.empty();
  @override
  final ValueListenable<bool> connected = ValueNotifier(false);
  @override
  void dispose() {}
}

/// Protocolo Pusher (Soketi o Pusher) con la conexión que indica /api/v1/meta. El canal
/// privado se autoriza con el token de la app en /api/v1/broadcasting/auth.
class PusherLiveFeed implements LiveFeed {
  PusherLiveFeed(ApiClient api, Map<String, dynamic> realtime, this.companyId) {
    final secure = realtime['scheme'] == 'https';
    final eventName = '${realtime['event'] ?? '.diets.changed'}'.replaceFirst(RegExp(r'^\.'), '');

    _client = PusherChannelsClient.websocket(
      options: PusherChannelsOptions.fromHost(
        scheme: secure ? 'wss' : 'ws',
        host: '${realtime['host']}',
        key: '${realtime['key']}',
        port: realtime['port'] is int ? realtime['port'] as int : int.tryParse('${realtime['port']}'),
      ),
      // Si se cae, reintenta; mientras tanto las pantallas consultan por su cuenta
      connectionErrorHandler: (error, trace, refresh) => refresh(),
      minimumReconnectDelayDuration: const Duration(seconds: 5),
    );
    final channel = _client.privateChannel(
      '${realtime['channel'] ?? 'private-diets'}',
      authorizationDelegate: _ApiAuthorization(api),
    );

    _subscriptions
      ..add(_client.lifecycleStream.listen((state) {
        _connected.value = state == PusherChannelsClientLifeCycleState.establishedConnection;
      }))
      ..add(_client.onConnectionEstablished.listen((_) => channel.subscribeIfNotUnsubscribed()))
      ..add(channel.bind(eventName).listen((event) {
        final kinds = parseEvent(event.tryGetDataAsMap(), companyId);
        if (kinds != null) _events.add(kinds);
      }));
    unawaited(_client.connect());
  }

  final int? companyId;
  late final PusherChannelsClient _client;
  final _events = StreamController<Set<String>>.broadcast();
  final _connected = ValueNotifier(false);
  final _subscriptions = <StreamSubscription<dynamic>>[];

  @override
  Stream<Set<String>> get events => _events.stream;

  @override
  ValueListenable<bool> get connected => _connected;

  /// Tipos de cambio del aviso, o null si es de otra empresa ([] en companies = todas)
  static Set<String>? parseEvent(Map<String, dynamic>? data, int? companyId) {
    if (data == null) return null;
    final companies = (data['companies'] as List? ?? const []).map((c) => int.tryParse('$c')).toList();
    if (companies.isNotEmpty && companyId != null && !companies.contains(companyId)) return null;
    return (data['kinds'] as List? ?? const []).map((k) => '$k').toSet();
  }

  @override
  void dispose() {
    for (final s in _subscriptions) {
      s.cancel();
    }
    _client.dispose();
    _events.close();
  }
}

class _ApiAuthorization implements EndpointAuthorizableChannelAuthorizationDelegate<PrivateChannelAuthorizationData> {
  _ApiAuthorization(this.api);

  final ApiClient api;

  @override
  EndpointAuthFailedCallback? get onAuthFailed => null;

  @override
  Future<PrivateChannelAuthorizationData> authorizationData(String socketId, String channelName) async {
    final data = await api.post('/broadcasting/auth', {'socket_id': socketId, 'channel_name': channelName});
    return PrivateChannelAuthorizationData(authKey: '${data['auth']}');
  }
}

/// Crea la conexión de la sesión (las pruebas la reemplazan por una simulada)
final liveFeedFactoryProvider = Provider<LiveFeed Function(ApiClient api, Meta? meta, int? companyId)>(
  (ref) => (api, meta, companyId) =>
      meta?.realtime['enabled'] == true ? PusherLiveFeed(api, meta!.realtime, companyId) : NoLiveFeed(),
);

/// Conexión de tiempo real del usuario y la empresa activos; se rehace al cambiar cualquiera
final liveFeedProvider = Provider.autoDispose<LiveFeed>((ref) {
  final key = ref.watch(sessionProvider.select((s) => s is LoggedIn ? '${s.me.id}-${s.me.currentCompanyId}' : null));
  if (key == null) return NoLiveFeed();

  final session = ref.read(sessionProvider.notifier);
  final feed = ref.read(liveFeedFactoryProvider)(session.client, session.meta, session.client.companyId);
  ref.onDispose(feed.dispose);
  return feed;
});

/// Refresca una pantalla al llegar un aviso de los tipos [kinds]. Sin WebSocket consulta cada
/// [fallback]; conectado, solo cada [safety] por seguridad. No consulta con la app minimizada
/// (como window.ntLive en la web).
class LiveTicker {
  LiveTicker(this.feed, {required this.kinds, required this.fallback, required this.onTick, this.safety = const Duration(seconds: 60)}) {
    _subscription = feed.events.listen((received) {
      if (received.any(kinds.contains)) _run();
    });
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      final every = feed.connected.value ? safety : fallback;
      if (clock.now().difference(_last) >= every && _visible) _run();
    });
  }

  final LiveFeed feed;
  final Set<String> kinds;
  final Duration fallback;
  final Duration safety;
  final Future<void> Function() onTick;

  late final StreamSubscription<Set<String>> _subscription;
  late final Timer _timer;
  DateTime _last = clock.now();

  bool get _visible {
    final state = WidgetsBinding.instance.lifecycleState;
    return state == null || state == AppLifecycleState.resumed || state == AppLifecycleState.inactive;
  }

  void _run() {
    _last = clock.now();
    onTick();
  }

  void dispose() {
    _subscription.cancel();
    _timer.cancel();
  }
}
