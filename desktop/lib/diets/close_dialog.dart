import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../core/api_exception.dart';
import '../core/theme.dart';
import 'models.dart';

/// Envío a otro lugar el día del alta
typedef Transfer = ({String destination, List<String> meals});

/// Cancelar o dar de alta con el resumen de cierre (NutritionDiets::startClose en la web):
/// despachos sin confirmar la recepción, lo recibido hoy y, en el alta, el envío opcional de
/// los tiempos de hoy a otro lugar. [onConfirm] recibe el envío (null si no hay). Devuelve el
/// mensaje de éxito o null si se cierra.
Future<String?> showCloseDialog(BuildContext context, {required Diet diet, required bool finish, required ClosingSummary summary, required Future<String> Function(Transfer? transfer) onConfirm}) =>
    showDialog<String>(
      context: context,
      builder: (_) => CloseDietDialog(diet: diet, finish: finish, summary: summary, onConfirm: onConfirm),
    );

class CloseDietDialog extends ConsumerStatefulWidget {
  const CloseDietDialog({super.key, required this.diet, required this.finish, required this.summary, required this.onConfirm});

  final Diet diet;
  final bool finish;
  final ClosingSummary summary;
  final Future<String> Function(Transfer? transfer) onConfirm;

  @override
  ConsumerState<CloseDietDialog> createState() => _CloseDietDialogState();
}

class _CloseDietDialogState extends ConsumerState<CloseDietDialog> {
  final _destination = TextEditingController();
  final _meals = <String>{};
  bool _transfer = false;
  bool _busy = false;
  String? _error;
  String? _destinationError;
  String? _mealsError;

  @override
  void dispose() {
    _destination.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    Transfer? transfer;
    if (_transfer) {
      setState(() {
        _destinationError = _destination.text.trim().isEmpty ? 'Indica a dónde se debe enviar la comida.' : null;
        _mealsError = _meals.isEmpty ? 'Elige al menos un tiempo de comida para enviar.' : null;
      });
      if (_destinationError != null || _mealsError != null) return;
      // En el orden del catálogo
      transfer = (destination: _destination.text.trim(), meals: widget.summary.candidates.keys.where(_meals.contains).toList());
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final message = await widget.onConfirm(transfer);
      if (mounted) Navigator.pop(context, message);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isUnauthorized) {
        Navigator.pop(context);
        await ref.read(sessionProvider.notifier).expired();
        return;
      }
      setState(() {
        _busy = false;
        _destinationError = e.field('transfer.destination');
        _mealsError = e.field('transfer.meals');
        _error = _destinationError == null && _mealsError == null ? e.message : null;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = widget.diet;
    final s = widget.summary;
    final finish = widget.finish;
    const muted = TextStyle(color: NtColors.textoSuave, fontSize: 13);
    final pending = s.pending.length;

    return AlertDialog(
      title: Text('${finish ? 'Dar de alta' : 'Cancelar dieta'} · Hab. ${d.habitation}'),
      content: SizedBox(
        width: 560,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (_error != null)
                _box(
                  Text(
                    _error!,
                    style: const TextStyle(color: NtColors.error, fontWeight: FontWeight.w600),
                  ),
                  NtColors.errorSuave,
                ),
              Text(
                finish
                    ? '¿Dar de alta a ${d.patient}? La dieta pasa a Finalizadas y deja de verse en dietas activas. El registro y su historial se conservan.'
                    : '¿Cancelar la dieta de ${d.patient}? Cocina deja de despacharla desde ahora.',
              ),
              const SizedBox(height: 12),
              // Despachos sin confirmar: salieron de cocina antes del cierre, así que se cobran
              if (pending > 0)
                _box(
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        pending == 1 ? 'Tiene 1 despacho sin confirmar la recepción:' : 'Tiene $pending despachos sin confirmar la recepción:',
                        style: const TextStyle(color: NtColors.advertencia, fontWeight: FontWeight.w700),
                      ),
                      for (final p in s.pending) Text('• ${p.meal} — ${p.today ? 'hoy' : p.date}, despachado ${p.at}', style: const TextStyle(color: NtColors.advertencia)),
                      const SizedBox(height: 4),
                      const Text('Ya salió de cocina, así que se cobrará. Confirma la recepción en «Recepción» para que quede en la facturación.', style: TextStyle(color: NtColors.advertencia)),
                    ],
                  ),
                  NtColors.advertenciaSuave,
                ),
              if (s.receivedToday.isNotEmpty)
                _box(
                  Text('Hoy ya se recibió: ${_join(s.receivedToday.map((m) => m.toLowerCase()).toList())}. Se cobra.', style: const TextStyle(color: NtColors.primarioSuaveTexto)),
                  NtColors.primarioSuave,
                ),
              if (pending == 0 && s.receivedToday.isEmpty)
                const Padding(
                  padding: EdgeInsets.only(bottom: 10),
                  child: Text('No tiene despachos de hoy ni recepciones pendientes.', style: muted),
                ),
              Text(
                finish
                    ? 'Después de la hora del alta no se despachan ni se cobran más tiempos, salvo los que se envíen a otro lugar.'
                    : 'El día de la cancelación solo se cobra lo despachado antes de cancelar y recibido.',
                style: muted,
              ),
              // Alta con envío a otro lugar (p. ej. remitido a otra IPS cercana)
              if (finish) ...[
                const Divider(height: 28),
                const Text('¿Se debe despachar la comida a otro lugar?', style: TextStyle(fontWeight: FontWeight.w700)),
                if (s.candidates.isEmpty)
                  const Padding(
                    padding: EdgeInsets.only(top: 6),
                    child: Text('No quedan tiempos de hoy por despachar.', style: muted),
                  )
                else ...[
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    value: _transfer,
                    onChanged: (v) => setState(() => _transfer = v ?? false),
                    title: const Text('Sí, enviar a otro lugar (por ejemplo, el paciente fue remitido a otra IPS cercana)'),
                  ),
                  if (_transfer) ...[
                    TextField(
                      controller: _destination,
                      maxLength: 150,
                      autofocus: true,
                      decoration: InputDecoration(labelText: 'Destino *', hintText: 'Ej.: IPS San José, sede norte — piso 2', errorText: _destinationError),
                    ),
                    const Text('Tiempos que se envían hoy *', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                    Wrap(
                      spacing: 4,
                      children: [
                        for (final e in s.candidates.entries)
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Checkbox(value: _meals.contains(e.key), onChanged: (v) => setState(() => v == true ? _meals.add(e.key) : _meals.remove(e.key))),
                              Text(e.value),
                              const SizedBox(width: 8),
                            ],
                          ),
                      ],
                    ),
                    if (_mealsError != null) Text(_mealsError!, style: const TextStyle(color: NtColors.error, fontSize: 12.5)),
                    const SizedBox(height: 4),
                    const Text('Cocina los verá en su lista con el destino; se cobran al confirmar la recepción.', style: muted),
                  ],
                ],
              ],
            ],
          ),
        ),
      ),
      actions: [
        OutlinedButton(onPressed: _busy ? null : () => Navigator.pop(context), child: const Text('Volver')),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: finish ? NtColors.btnInfo : NtColors.btnDanger, foregroundColor: finish ? Colors.black : Colors.white),
          onPressed: _busy ? null : _submit,
          child: Text(finish ? 'Dar de alta' : 'Cancelar dieta'),
        ),
      ],
    );
  }

  Widget _box(Widget child, Color background) => Container(
    width: double.infinity,
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(8)),
    child: child,
  );

  /// «desayuno, almuerzo y cena»
  static String _join(List<String> items) => items.length <= 1 ? items.join() : '${items.sublist(0, items.length - 1).join(', ')} y ${items.last}';
}
