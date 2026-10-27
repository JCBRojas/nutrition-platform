import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../common/widgets.dart';
import '../core/catalogs.dart';
import '../core/theme.dart';
import 'history_controller.dart';

/// «Historial de cambios»: a la izquierda las dietas (búsqueda y estado); a la derecha las
/// versiones de la elegida, de la más reciente a la más antigua, con lo agregado y lo quitado.
class HistoryScreen extends ConsumerStatefulWidget {
  const HistoryScreen({super.key});

  @override
  ConsumerState<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends ConsumerState<HistoryScreen> {
  final _search = TextEditingController();
  Timer? _debounce;

  HistoryController get _controller => ref.read(historyProvider.notifier);

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(historyProvider);

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Text('Historial de cambios', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(width: 12),
              IconButton(
                tooltip: 'Actualizar',
                onPressed: () {
                  _controller.refresh();
                  if (state.selected != null) _controller.select(state.selected!.id);
                },
                icon: const Icon(Icons.refresh),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Expanded(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  width: 340,
                  child: _DietList(
                    state: state,
                    search: _search,
                    onSearch: (value) {
                      _debounce?.cancel();
                      _debounce = Timer(const Duration(milliseconds: 300), () => _controller.setQuery(value));
                    },
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(child: _Timeline(state: state)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DietList extends ConsumerWidget {
  const _DietList({required this.state, required this.search, required this.onSearch});

  final HistoryState state;
  final TextEditingController search;
  final ValueChanged<String> onSearch;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(historyProvider.notifier);
    final diets = state.diets;

    // Material (no Container con fondo): ListTile pinta la selección en el Material más cercano
    return Material(
      color: NtColors.superficie,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: NtColors.borde),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: search,
              onChanged: onSearch,
              decoration: const InputDecoration(isDense: true, prefixIcon: Icon(Icons.search), hintText: 'Buscar habitación o paciente'),
            ),
            const SizedBox(height: 8),
            DropdownButton<String>(
              value: state.status,
              isExpanded: true,
              underline: const SizedBox.shrink(),
              items: const [
                DropdownMenuItem(value: 'all', child: Text('Todas las dietas')),
                DropdownMenuItem(value: 'active', child: Text('Activas')),
                DropdownMenuItem(value: 'cancelled', child: Text('Canceladas')),
                DropdownMenuItem(value: 'finished', child: Text('Finalizadas (alta)')),
              ],
              onChanged: (v) => controller.setStatus(v ?? 'all'),
            ),
            const Divider(height: 12),
            if (state.error != null) LoadError(message: state.error!, onRetry: controller.refresh),
            Expanded(
              child: diets == null
                  ? (state.error != null ? const SizedBox.shrink() : const Center(child: CircularProgressIndicator()))
                  : diets.isEmpty
                  ? Center(
                      child: Text(
                        state.query.isEmpty ? 'No hay dietas.' : 'No hay dietas que coincidan con «${state.query}».',
                        style: const TextStyle(color: NtColors.textoSuave),
                        textAlign: TextAlign.center,
                      ),
                    )
                  : ListView.separated(
                      itemCount: diets.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 2),
                      itemBuilder: (context, i) {
                        final d = diets[i];
                        return ListTile(
                          key: ValueKey('history-diet-${d.id}'),
                          dense: true,
                          selected: state.selected?.id == d.id,
                          selectedTileColor: NtColors.primarioSuave,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          leading: RoomBadge(d.habitation),
                          title: Text(d.patient, overflow: TextOverflow.ellipsis),
                          subtitle: Text('${d.versions} ${d.versions == 1 ? 'versión' : 'versiones'} · ${d.statusLabel}'),
                          onTap: () => controller.select(d.id),
                        );
                      },
                    ),
            ),
            if (state.lastPage > 1)
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  IconButton(tooltip: 'Anterior', onPressed: state.page > 1 ? () => controller.setPage(state.page - 1) : null, icon: const Icon(Icons.chevron_left)),
                  Text('Página ${state.page} de ${state.lastPage}', style: const TextStyle(fontSize: 13)),
                  IconButton(tooltip: 'Siguiente', onPressed: state.page < state.lastPage ? () => controller.setPage(state.page + 1) : null, icon: const Icon(Icons.chevron_right)),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

class _Timeline extends ConsumerWidget {
  const _Timeline({required this.state});

  final HistoryState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final diet = state.selected;
    if (diet == null) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.history, size: 40, color: NtColors.textoSuave),
            SizedBox(height: 8),
            Text('Elige una dieta para ver sus versiones.', style: TextStyle(color: NtColors.textoSuave)),
          ],
        ),
      );
    }
    final versions = state.versions;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 10,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            RoomBadge(diet.habitation),
            Text(diet.patient, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
            Text(diet.statusLabel, style: const TextStyle(color: NtColors.textoSuave)),
          ],
        ),
        const SizedBox(height: 10),
        if (state.timelineError != null) LoadError(message: state.timelineError!, onRetry: () => ref.read(historyProvider.notifier).select(diet.id)),
        Expanded(
          child: versions == null
              ? const Center(child: CircularProgressIndicator())
              : ListView.separated(
                  itemCount: versions.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 10),
                  itemBuilder: (context, i) => _VersionCard(entry: versions[i]),
                ),
        ),
      ],
    );
  }
}

class _VersionCard extends StatelessWidget {
  const _VersionCard({required this.entry});

  final VersionEntry entry;

  static const _types = {
    'creacion': ('Creación', NtColors.exito, NtColors.exitoSuave),
    'edicion': ('Edición', NtColors.primarioSuaveTexto, NtColors.primarioSuave),
    'cancelacion': ('Cancelación', NtColors.error, NtColors.errorSuave),
    'finalizacion': ('Alta (finalizada)', NtColors.primarioSuaveTexto, NtColors.primarioSuave),
    'completada': ('Día completado', NtColors.textoSuave, NtColors.fondo),
    'continuacion': ('Continuación', NtColors.textoSuave, NtColors.fondo),
  };

  static const _labels = {
    'timeFood': 'Tiempos de comida',
    'consistency': 'Consistencia',
    'specifications': 'Especificaciones',
    'billing_type': 'Tipo de dieta',
    'observations': 'Observaciones',
    'isolation': 'Aislamiento',
  };

  static const _kinds = {'timeFood': TagKind.meal, 'consistency': TagKind.consistency, 'specifications': TagKind.specification};

  Widget _row(String field, Widget value, {bool changed = false}) => Padding(
    padding: const EdgeInsets.only(top: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 150,
          child: Text(
            _labels[field]!,
            style: TextStyle(color: changed ? NtColors.texto : NtColors.textoSuave, fontSize: 13, fontWeight: changed ? FontWeight.w700 : FontWeight.w500),
          ),
        ),
        Expanded(child: value),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final (typeLabel, fg, bg) = _types[entry.type] ?? ('Edición', NtColors.primarioSuaveTexto, NtColors.primarioSuave);
    // Las copias del día (completada, continuada) no traen cambio clínico: solo el encabezado
    final dayCopy = (entry.type == 'completada' || entry.type == 'continuacion') && !entry.hasChanges;

    return Container(
      key: ValueKey('version-${entry.version}'),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: NtColors.superficie,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: NtColors.borde),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 10,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text('Versión ${entry.version}', style: const TextStyle(fontWeight: FontWeight.w700)),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(6)),
                child: Text(
                  typeLabel,
                  style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w600),
                ),
              ),
              Text('${formatWhen(entry.createdAt)}${entry.updatedBy != null ? ' · ${entry.updatedBy}' : ''}', style: const TextStyle(color: NtColors.textoSuave, fontSize: 13)),
            ],
          ),
          if (dayCopy)
            const Padding(
              padding: EdgeInsets.only(top: 6),
              child: Text('Sin cambios en la dieta.', style: TextStyle(color: NtColors.textoSuave, fontSize: 13)),
            )
          else ...[
            for (final f in VersionEntry.listFields)
              _row(
                f,
                entry.lists[f]!.isEmpty
                    ? const Text('—')
                    : Wrap(
                        spacing: 4,
                        runSpacing: 4,
                        children: [
                          for (final item in entry.lists[f]!)
                            NtTag(
                              item.label,
                              kind: _kinds[f]!,
                              change: switch (item.state) {
                                'added' => TagChange.added,
                                'removed' => TagChange.removed,
                                _ => TagChange.none,
                              },
                            ),
                        ],
                      ),
                changed: entry.listChanged[f] ?? false,
              ),
            for (final f in VersionEntry.scalarFields) _row(f, _scalar(entry.scalars[f]!), changed: entry.scalars[f]!.changed),
          ],
        ],
      ),
    );
  }

  Widget _scalar(ScalarField field) {
    final value = (field.value == null || field.value!.isEmpty) ? '—' : field.value!;
    if (!field.changed) return Text(value, style: const TextStyle(fontSize: 13));
    final old = (field.old == null || field.old!.isEmpty) ? '—' : field.old!;
    return Wrap(
      spacing: 6,
      children: [
        Text(
          old,
          style: const TextStyle(fontSize: 13, color: NtColors.textoSuave, decoration: TextDecoration.lineThrough),
        ),
        const Text('→', style: TextStyle(fontSize: 13, color: NtColors.textoSuave)),
        Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
      ],
    );
  }
}
