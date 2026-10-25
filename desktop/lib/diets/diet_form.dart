import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../core/api_exception.dart';
import '../core/catalogs.dart';
import '../core/theme.dart';
import 'models.dart';

/// Registrar (sin [diet]) o editar una dieta (nueva versión), como los modales de la web
/// (modal-create-diets y modal-edit-diets). Envía con [onSubmit] y, si el servidor responde
/// 422, muestra cada error junto a su campo. Devuelve el mensaje de éxito o null si se cierra.
Future<String?> showDietForm(BuildContext context, {required Catalogs catalogs, required Future<String> Function(DietInput input) onSubmit, Diet? diet, Set<String> activeRooms = const {}}) =>
    showDialog<String>(
      context: context,
      barrierDismissible: false,
      builder: (_) => DietFormDialog(catalogs: catalogs, onSubmit: onSubmit, diet: diet, activeRooms: activeRooms),
    );

class DietFormDialog extends ConsumerStatefulWidget {
  const DietFormDialog({super.key, required this.catalogs, required this.onSubmit, this.diet, this.activeRooms = const {}});

  final Catalogs catalogs;
  final Future<String> Function(DietInput input) onSubmit;
  final Diet? diet;
  final Set<String> activeRooms;

  @override
  ConsumerState<DietFormDialog> createState() => _DietFormDialogState();
}

class _DietFormDialogState extends ConsumerState<DietFormDialog> {
  final _room = TextEditingController();
  final _patient = TextEditingController();
  final _observations = TextEditingController();
  late final Set<String> _meals;
  late final Set<String> _consistency;
  late final Set<String> _specifications;
  late String _isolation;
  String? _billingType;
  late String _changes;
  bool _allowDuplicate = false;

  /// Habitación que el servidor rechazó por tener ya una dieta activa (la lista pudo estar desactualizada)
  String? _takenByServer;
  bool _busy = false;
  String? _error;
  Map<String, String> _fieldErrors = const {};

  bool get _creating => widget.diet == null;
  Catalogs get _c => widget.catalogs;

  @override
  void initState() {
    super.initState();
    final d = widget.diet;
    _meals = {...?d?.timeFood.map(Catalogs.normalizeMeal)};
    _consistency = {...?d?.consistency};
    _specifications = {...?d?.specifications};
    _observations.text = d?.observations ?? '';
    _isolation = d?.isolated == true ? 'Sí' : 'No';
    _billingType = _c.billingTypes.containsKey(d?.billingType) ? d!.billingType : null;
    _changes = d?.changes == 'con_cambios' ? 'con_cambios' : 'NA';
    _room.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _room.dispose();
    _patient.dispose();
    _observations.dispose();
    super.dispose();
  }

  bool get _roomTaken {
    final room = _room.text.trim();
    return _creating && room.isNotEmpty && (widget.activeRooms.contains(room) || _takenByServer == room);
  }

  static const _fields = {'habitation', 'name_patient', 'timeFood', 'consistency', 'specifications', 'observations', 'isolation', 'billing_type', 'changes'};

  /// Las mismas comprobaciones que modal-render-chips.js antes de enviar; el servidor las repite
  Map<String, String> _validate() => {
    if (_creating && _room.text.trim().isEmpty) 'habitation': 'Escribe la habitación.',
    if (_creating && _patient.text.trim().isEmpty) 'name_patient': 'Escribe el nombre del paciente.',
    if (_meals.isEmpty) 'timeFood': 'Elige al menos un tiempo de comida.',
    if (_consistency.isEmpty) 'consistency': 'Elige al menos una consistencia.',
    if (_specifications.isEmpty) 'specifications': 'Elige al menos una especificación.',
    if (_c.billingTypeRequired && _billingType == null) 'billing_type': 'Elige el tipo de dieta (facturación).',
  };

  Future<void> _submit() async {
    final errors = _validate();
    if (errors.isNotEmpty) {
      setState(() {
        _fieldErrors = errors;
        _error = null;
      });
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _fieldErrors = const {};
    });
    // Orden del catálogo, no el de selección
    List<String> ordered(Iterable<String> keys, Set<String> chosen) => keys.where(chosen.contains).toList();
    final input = DietInput(
      habitation: _room.text,
      patient: _patient.text,
      timeFood: ordered(_c.meals.map((m) => m.key), _meals),
      consistency: ordered(_c.consistency.keys, _consistency),
      specifications: ordered(_c.specifications.keys, _specifications),
      observations: _observations.text,
      isolation: _isolation,
      billingType: _c.billingTypeRequired ? _billingType : null,
      changes: _creating ? null : _changes,
      allowDuplicateRoom: _allowDuplicate,
    );
    try {
      final message = await widget.onSubmit(input);
      if (mounted) Navigator.pop(context, message);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isUnauthorized) {
        Navigator.pop(context);
        await ref.read(sessionProvider.notifier).expired();
        return;
      }
      // «timeFood.0» → «timeFood»: el error se muestra en el grupo
      final fields = <String, String>{};
      e.fieldErrors.forEach((key, messages) => fields.putIfAbsent(key.split('.').first, () => messages.first));
      setState(() {
        _busy = false;
        _fieldErrors = fields;
        _error = fields.keys.any(_fields.contains) ? null : e.message;
        if (fields['habitation']?.contains('Registrar de todas formas') ?? false) _takenByServer = _room.text.trim();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = widget.diet;
    return AlertDialog(
      title: Text(_creating ? 'Registrar dieta' : 'Editar dieta · Hab. ${d!.habitation}'),
      content: SizedBox(
        width: 620,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (_error != null) _Box(_error!, color: NtColors.error, background: NtColors.errorSuave),
              if (_creating) ...[
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 150,
                      child: TextField(
                        controller: _room,
                        autofocus: true,
                        maxLength: _c.habitationMax,
                        decoration: InputDecoration(labelText: 'Habitación *', errorText: _fieldErrors['habitation']),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: TextField(
                        controller: _patient,
                        maxLength: _c.nameMax,
                        decoration: InputDecoration(labelText: 'Nombre del paciente *', errorText: _fieldErrors['name_patient']),
                      ),
                    ),
                  ],
                ),
                if (_roomTaken) ...[
                  _Box(
                    'La habitación ${_room.text.trim()} ya tiene una dieta activa. Edítala desde la lista o, si es una habitación compartida, marca «Registrar de todas formas».',
                    color: NtColors.advertencia,
                    background: NtColors.advertenciaSuave,
                  ),
                  CheckboxListTile(
                    key: const ValueKey('allow-duplicate-room'),
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    value: _allowDuplicate,
                    onChanged: (v) => setState(() => _allowDuplicate = v ?? false),
                    title: const Text('Registrar de todas formas (habitación compartida)'),
                  ),
                ],
              ] else
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(d!.patient, style: const TextStyle(fontWeight: FontWeight.w600)),
                ),
              _ChipGroup(
                title: 'Tiempos de comida *',
                options: {for (final m in _c.meals) m.key: m.label},
                selected: _meals,
                color: NtColors.dietaTiempo,
                background: NtColors.dietaTiempoSuave,
                error: _fieldErrors['timeFood'],
                onChanged: () => setState(() {}),
              ),
              _ChipGroup(
                title: 'Consistencia *',
                options: _c.consistency,
                selected: _consistency,
                color: NtColors.dietaConsistencia,
                background: NtColors.dietaConsistenciaSuave,
                error: _fieldErrors['consistency'],
                onChanged: () => setState(() {}),
              ),
              _ChipGroup(
                title: 'Especificaciones *',
                options: _c.specifications,
                selected: _specifications,
                color: NtColors.dietaEspecificacion,
                background: NtColors.dietaEspecificacionSuave,
                error: _fieldErrors['specifications'],
                onChanged: () => setState(() {}),
              ),
              TextField(
                controller: _observations,
                minLines: 1,
                maxLines: 3,
                decoration: InputDecoration(labelText: 'Observaciones', hintText: 'Notas adicionales para cocina…', errorText: _fieldErrors['observations']),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 16,
                runSpacing: 12,
                children: [
                  SizedBox(
                    width: 160,
                    child: DropdownButtonFormField<String>(
                      initialValue: _isolation,
                      isExpanded: true,
                      decoration: InputDecoration(labelText: 'Aislamiento', errorText: _fieldErrors['isolation']),
                      items: const [
                        DropdownMenuItem(value: 'No', child: Text('No')),
                        DropdownMenuItem(value: 'Sí', child: Text('Sí')),
                      ],
                      onChanged: (v) => setState(() => _isolation = v ?? 'No'),
                    ),
                  ),
                  if (_c.billingTypeRequired)
                    SizedBox(
                      width: 260,
                      child: DropdownButtonFormField<String>(
                        initialValue: _billingType,
                        isExpanded: true,
                        decoration: InputDecoration(labelText: 'Tipo de dieta (facturación) *', errorText: _fieldErrors['billing_type']),
                        items: [
                          for (final e in _c.billingTypes.entries)
                            DropdownMenuItem(
                              value: e.key,
                              child: Text(e.value, overflow: TextOverflow.ellipsis),
                            ),
                        ],
                        onChanged: (v) => setState(() => _billingType = v),
                      ),
                    ),
                  if (!_creating)
                    SizedBox(
                      width: 200,
                      child: DropdownButtonFormField<String>(
                        initialValue: _changes,
                        isExpanded: true,
                        decoration: InputDecoration(labelText: 'Cambios en la dieta', errorText: _fieldErrors['changes']),
                        items: const [
                          DropdownMenuItem(value: 'NA', child: Text('N/A')),
                          DropdownMenuItem(value: 'con_cambios', child: Text('Con cambios')),
                        ],
                        onChanged: (v) => setState(() => _changes = v ?? 'NA'),
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
      actions: [
        OutlinedButton(onPressed: _busy ? null : () => Navigator.pop(context), child: const Text('Cancelar')),
        FilledButton(onPressed: _busy || (_roomTaken && !_allowDuplicate) ? null : _submit, child: Text(_creating ? 'Registrar dieta' : 'Actualizar')),
      ],
    );
  }
}

/// Selección múltiple del catálogo con los colores originales de las etiquetas (NUT-CMP-001 §2c)
class _ChipGroup extends StatelessWidget {
  const _ChipGroup({required this.title, required this.options, required this.selected, required this.color, required this.background, required this.onChanged, this.error});

  final String title;
  final Map<String, String> options;
  final Set<String> selected;
  final Color color;
  final Color background;
  final VoidCallback onChanged;
  final String? error;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
        const SizedBox(height: 6),
        Wrap(
          spacing: 6,
          runSpacing: 6,
          children: [
            for (final e in options.entries)
              FilterChip(
                label: Text(e.value),
                selected: selected.contains(e.key),
                showCheckmark: true,
                checkmarkColor: color,
                selectedColor: background,
                side: BorderSide(color: selected.contains(e.key) ? color : NtColors.borde),
                labelStyle: TextStyle(color: selected.contains(e.key) ? color : NtColors.texto, fontWeight: FontWeight.w600, fontSize: 13),
                onSelected: (on) {
                  on ? selected.add(e.key) : selected.remove(e.key);
                  onChanged();
                },
              ),
          ],
        ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(error!, style: const TextStyle(color: NtColors.error, fontSize: 12.5)),
          ),
      ],
    ),
  );
}

class _Box extends StatelessWidget {
  const _Box(this.text, {required this.color, required this.background});

  final String text;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(8)),
    child: Text(
      text,
      style: TextStyle(color: color, fontWeight: FontWeight.w600),
    ),
  );
}
