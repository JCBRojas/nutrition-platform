import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/theme.dart';
import '../live/alerts.dart';
import '../live/live_feed.dart';

/// Tipo de etiqueta del catálogo de dietas (colores originales, NUT-CMP-001 §2c)
enum TagKind { meal, consistency, specification }

/// Cambio respecto de la última versión confirmada por cocina
enum TagChange { none, added, removed }

/// Estado del tiempo de comida en el día (x-meal-tag en la web)
enum Delivery { none, dispatched, received }

class NtTag extends StatelessWidget {
  const NtTag(this.label, {super.key, required this.kind, this.change = TagChange.none, this.delivery = Delivery.none});

  final String label;
  final TagKind kind;
  final TagChange change;
  final Delivery delivery;

  @override
  Widget build(BuildContext context) {
    var (fg, bg) = switch (kind) {
      TagKind.meal => (NtColors.dietaTiempo, NtColors.dietaTiempoSuave),
      TagKind.consistency => (NtColors.dietaConsistencia, NtColors.dietaConsistenciaSuave),
      TagKind.specification => (NtColors.dietaEspecificacion, NtColors.dietaEspecificacionSuave),
    };
    Border? border;
    IconData? icon;
    var semantic = label;
    if (delivery == Delivery.received) {
      (fg, bg, icon) = (Colors.white, NtColors.dietaEntregado, Icons.check);
      semantic = '$label, entregado';
    } else if (delivery == Delivery.dispatched) {
      border = Border.all(color: NtColors.dietaEntregado, width: 1.5);
      icon = Icons.local_shipping_outlined;
      semantic = '$label, despachado sin recibir';
    }
    if (change == TagChange.added) {
      border = Border.all(color: fg, width: 2);
      semantic = '$label, agregado';
    } else if (change == TagChange.removed) {
      (fg, bg) = (NtColors.textoSuave, NtColors.fondo);
      semantic = '$label, quitado';
    }

    return Semantics(
      label: semantic,
      excludeSemantics: true,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
        decoration: BoxDecoration(color: bg, border: border, borderRadius: BorderRadius.circular(6)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          if (change == TagChange.added) Text('+ ', style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w800)),
          if (icon != null) ...[Icon(icon, size: 12, color: delivery == Delivery.received ? Colors.white : NtColors.dietaEntregado), const SizedBox(width: 3)],
          // Flexible: en una columna angosta la etiqueta pasa a dos líneas en lugar de desbordarse
          Flexible(
            child: Text(label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: fg,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  decoration: change == TagChange.removed ? TextDecoration.lineThrough : null,
                )),
          ),
        ]),
      ),
    );
  }
}

class RoomBadge extends StatelessWidget {
  const RoomBadge(this.room, {super.key});

  final String room;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: NtColors.primarioSuave, borderRadius: BorderRadius.circular(6)),
        child: Text(room, style: const TextStyle(color: NtColors.primarioSuaveTexto, fontWeight: FontWeight.w700)),
      );
}

/// Diálogo de confirmación (en lugar de confirm() de la web). Devuelve true si confirma.
Future<bool> confirmDialog(BuildContext context, {required String title, required String message, required String confirm, bool danger = false}) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(title),
      content: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 440), child: Text(message)),
      actions: [
        OutlinedButton(onPressed: () => Navigator.pop(context, false), child: const Text('Volver')),
        FilledButton(
          style: danger ? FilledButton.styleFrom(backgroundColor: NtColors.btnDanger) : null,
          autofocus: true,
          onPressed: () => Navigator.pop(context, true),
          child: Text(confirm),
        ),
      ],
    ),
  );
  return ok == true;
}

/// «No llegó» o «Anular despacho»: confirmación con motivo opcional (x-modal-void-dispatch).
/// Devuelve el motivo ('' si no escribió) o null si canceló.
Future<String?> voidDialog(BuildContext context, {required String title, required String message, required String confirm}) {
  final reason = TextEditingController();
  return showDialog<String>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(title),
      content: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 440),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(message),
          const SizedBox(height: 14),
          TextField(
            controller: reason,
            maxLength: 255,
            autofocus: true,
            decoration: const InputDecoration(labelText: 'Motivo (opcional)'),
          ),
        ]),
      ),
      actions: [
        OutlinedButton(onPressed: () => Navigator.pop(context), child: const Text('Volver')),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: NtColors.btnDanger),
          onPressed: () => Navigator.pop(context, reason.text.trim()),
          child: Text(confirm),
        ),
      ],
    ),
  );
}

/// Indicador con franja de color (stats-grid de la web)
class StatCard extends StatelessWidget {
  const StatCard({super.key, required this.label, required this.value, required this.sub, required this.accent, this.width = 230});

  final String label;
  final String value;
  final String sub;
  final Color accent;

  /// null: ocupa el ancho que le dé el padre (p. ej. dentro de un Expanded)
  final double? width;

  @override
  Widget build(BuildContext context) => Container(
        width: width,
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
        // Bordes de distinto color no admiten borderRadius: franja de color como sombra interior
        decoration: BoxDecoration(
          color: NtColors.superficie,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: NtColors.borde),
          boxShadow: [BoxShadow(color: accent, offset: const Offset(-4, 0), spreadRadius: -1)],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(color: NtColors.textoSuave, fontSize: 12.5, fontWeight: FontWeight.w600)),
          Text(value, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700)),
          Text(sub, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: NtColors.textoSuave, fontSize: 12)),
        ]),
      );
}

/// Aviso breve abajo (resultado de una acción), con un botón opcional (p. ej. «Abrir»)
void showNotice(BuildContext context, String message, {bool error = false, SnackBarAction? action}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: error ? NtColors.error : NtColors.texto,
      behavior: SnackBarBehavior.floating,
      width: 460,
      action: action,
      duration: Duration(seconds: action == null ? 5 : 8),
    ));
}

/// Paginación común: «1–15 de N registros», Anterior/Siguiente y filas por página (15/30/50)
class PaginationBar extends StatelessWidget {
  const PaginationBar({super.key, required this.page, required this.lastPage, required this.perPage, required this.total, required this.onPage, required this.onPerPage});

  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final ValueChanged<int> onPage;
  final ValueChanged<int> onPerPage;

  @override
  Widget build(BuildContext context) {
    final from = total == 0 ? 0 : (page - 1) * perPage + 1;
    final to = (page * perPage).clamp(0, total);

    return Wrap(spacing: 16, crossAxisAlignment: WrapCrossAlignment.center, children: [
      Text('$from–$to de $total registros', style: const TextStyle(color: NtColors.textoSuave)),
      DropdownButton<int>(
        value: perPage,
        underline: const SizedBox.shrink(),
        items: [for (final n in const [15, 30, 50]) DropdownMenuItem(value: n, child: Text('$n por página'))],
        onChanged: (n) => n == null ? null : onPerPage(n),
      ),
      Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(tooltip: 'Anterior', onPressed: page > 1 ? () => onPage(page - 1) : null, icon: const Icon(Icons.chevron_left)),
        Text('Página $page de ${lastPage < 1 ? 1 : lastPage}'),
        IconButton(tooltip: 'Siguiente', onPressed: page < lastPage ? () => onPage(page + 1) : null, icon: const Icon(Icons.chevron_right)),
      ]),
    ]);
  }
}

/// «En vivo» con WebSocket o «Actualiza cada N s» con la consulta de respaldo
class LiveStatus extends ConsumerWidget {
  const LiveStatus({super.key, required this.fallbackSeconds});

  final int fallbackSeconds;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final feed = ref.watch(liveFeedProvider);
    return ValueListenableBuilder<bool>(
      valueListenable: feed.connected,
      builder: (context, live, _) => Tooltip(
        message: live ? 'Los cambios llegan al instante.' : 'Sin conexión en vivo: se consulta el servidor cada $fallbackSeconds s.',
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.circle, size: 10, color: live ? NtColors.exito : NtColors.textoSuave),
          const SizedBox(width: 6),
          Text(live ? 'En vivo' : 'Actualiza cada $fallbackSeconds s', style: const TextStyle(color: NtColors.textoSuave, fontSize: 13)),
        ]),
      ),
    );
  }
}

/// Activa o desactiva el sonido y la notificación del sistema
class AlertsToggle extends ConsumerWidget {
  const AlertsToggle({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final enabled = ref.watch(alertsEnabledProvider);
    return IconButton(
      tooltip: enabled ? 'Alertas activadas (sonido y notificación). Pulsa para silenciar.' : 'Alertas silenciadas. Pulsa para activarlas.',
      isSelected: enabled,
      icon: const Icon(Icons.notifications_off_outlined),
      selectedIcon: const Icon(Icons.notifications_active_outlined, color: NtColors.primarioSuaveTexto),
      onPressed: () => ref.read(alertsEnabledProvider.notifier).set(!enabled),
    );
  }
}

/// Mensaje de error de carga con botón para reintentar
class LoadError extends StatelessWidget {
  const LoadError({super.key, required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: NtColors.errorSuave, borderRadius: BorderRadius.circular(8)),
        child: Row(children: [
          const Icon(Icons.error_outline, color: NtColors.error),
          const SizedBox(width: 8),
          Expanded(child: Text(message, style: const TextStyle(color: NtColors.error, fontWeight: FontWeight.w600))),
          TextButton(onPressed: onRetry, child: const Text('Reintentar')),
        ]),
      );
}
