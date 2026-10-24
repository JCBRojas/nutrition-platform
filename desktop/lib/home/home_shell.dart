import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../auth/auth_screens.dart';
import '../auth/models.dart';
import '../auth/session.dart';
import '../core/theme.dart';

/// Sección del menú lateral, visible según los permisos (como config/navigation.php en la web)
class Section {
  const Section(this.id, this.label, this.icon, this.visible);

  final String id;
  final String label;
  final IconData icon;
  final bool Function(Me me) visible;
}

final sections = <Section>[
  Section('dispatch', 'Despacho', Icons.local_shipping_outlined, (me) => me.can('confirmDelivery')),
  Section('receipts', 'Recepción', Icons.fact_check_outlined, (me) => me.can('confirmReception')),
  Section('diets', 'Dietas activas', Icons.grid_view_outlined, (me) => me.can('viewActiveDiets')),
  Section('dispatches', 'Despachos del día', Icons.event_note_outlined, (me) => me.can('viewDispatches') && me.hasPremium('dispatch_log')),
];

/// Marco de la app con sesión: menú lateral, empresa activa, usuario y cierre de sesión.
/// El contenido de cada sección llega en los pasos 5 y 6 del plan.
class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key, required this.me, this.builders = const {}});

  final Me me;

  /// Pantalla de cada sección por id; las que no están muestran un aviso
  final Map<String, WidgetBuilder> builders;

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  late String _current = _initial();

  List<Section> get _visible => sections.where((s) => s.visible(widget.me)).toList();

  String _initial() {
    final visible = _visible;
    final home = widget.me.home == 'dispatch' ? 'dispatch' : (widget.me.home == 'diets' ? 'diets' : widget.me.home);
    return visible.any((s) => s.id == home) ? home : (visible.isNotEmpty ? visible.first.id : '');
  }

  Future<void> _openWeb(String path) async {
    final base = ref.read(sessionProvider.notifier).meta?.webUrl ?? '';
    await launchUrl(Uri.parse('$base$path'));
  }

  Future<void> _confirmLogout() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('¿Cerrar sesión?'),
        content: const Text('Tendrás que volver a ingresar tu correo y contraseña.'),
        actions: [
          OutlinedButton(onPressed: () => Navigator.pop(context, false), child: const Text('Volver')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Cerrar sesión')),
        ],
      ),
    );
    if (ok == true) await ref.read(sessionProvider.notifier).logout();
  }

  @override
  Widget build(BuildContext context) {
    final me = widget.me;
    final visible = _visible;
    final index = visible.indexWhere((s) => s.id == _current);

    return Scaffold(
      appBar: AppBar(
        title: const Text('NutriTrack', style: TextStyle(color: NtColors.marcaTexto, fontWeight: FontWeight.w700)),
        actions: [
          CompanySwitcher(me: me),
          const SizedBox(width: 12),
          Text(me.name, style: const TextStyle(color: NtColors.textoSuave)),
          IconButton(tooltip: 'Abrir NutriTrack en el navegador', icon: const Icon(Icons.open_in_browser), onPressed: () => _openWeb('')),
          IconButton(tooltip: 'Cerrar sesión', icon: const Icon(Icons.logout), onPressed: _confirmLogout),
          const SizedBox(width: 8),
        ],
      ),
      body: Row(
        children: [
          if (visible.isNotEmpty)
            NavigationRail(
              selectedIndex: index < 0 ? null : index,
              labelType: NavigationRailLabelType.all,
              onDestinationSelected: (i) => setState(() => _current = visible[i].id),
              destinations: [
                for (final s in visible) NavigationRailDestination(icon: Icon(s.icon), label: Text(s.label)),
              ],
            ),
          const VerticalDivider(width: 1),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (me.needsWebAction)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
                    child: _ComplianceNotice(me: me, onOpen: _openWeb),
                  ),
                Expanded(
                  child: visible.isEmpty
                      ? _Placeholder(
                          title: 'Sin secciones en la app',
                          text: 'Tu rol no tiene funciones de la operación diaria. Usa NutriTrack en el navegador.',
                          action: FilledButton(onPressed: () => _openWeb(''), child: const Text('Abrir en el navegador')),
                        )
                      : (widget.builders[_current]?.call(context) ??
                          _Placeholder(title: visible[index < 0 ? 0 : index].label, text: 'Esta sección se agrega en el siguiente paso de la app.')),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Empresa activa: con varias, un selector; con una, su nombre
class CompanySwitcher extends ConsumerWidget {
  const CompanySwitcher({super.key, required this.me});

  final Me me;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final current = me.currentCompany;
    if (me.companies.length <= 1) {
      return Text(current?.name ?? 'Sin empresa', style: const TextStyle(fontWeight: FontWeight.w600));
    }

    return DropdownButton<int>(
      value: current?.id,
      underline: const SizedBox.shrink(),
      hint: const Text('Empresa'),
      items: [for (final c in me.companies) DropdownMenuItem(value: c.id, child: Text(c.name))],
      onChanged: (id) {
        if (id != null && id != current?.id) ref.read(sessionProvider.notifier).selectCompany(id);
      },
    );
  }
}

class _ComplianceNotice extends StatelessWidget {
  const _ComplianceNotice({required this.me, required this.onOpen});

  final Me me;
  final Future<void> Function(String path) onOpen;

  @override
  Widget build(BuildContext context) {
    final privacy = me.privacyRequired;
    final message = privacy
        ? 'Debes aceptar la política de tratamiento de datos en NutriTrack web antes de usar la app.'
        : 'Tu rol requiere la verificación en dos pasos. Actívala en tu perfil de NutriTrack web.';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: NtColors.advertenciaSuave, borderRadius: BorderRadius.circular(8)),
      child: Row(children: [
        const Icon(Icons.info_outline, color: NtColors.advertencia),
        const SizedBox(width: 10),
        Expanded(child: Text(message, style: const TextStyle(color: NtColors.advertencia, fontWeight: FontWeight.w600))),
        TextButton(
          onPressed: () => onOpen(privacy ? '/politica-de-datos/aceptar' : '/user/profile#two-factor'),
          child: const Text('Abrir en el navegador'),
        ),
        Consumer(builder: (context, ref, _) => TextButton(onPressed: () => ref.read(sessionProvider.notifier).refreshMe(), child: const Text('Ya lo hice'))),
      ]),
    );
  }
}

class _Placeholder extends StatelessWidget {
  const _Placeholder({required this.title, required this.text, this.action});

  final String title;
  final String text;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Text(title, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 8),
        Text(text, style: const TextStyle(color: NtColors.textoSuave), textAlign: TextAlign.center),
        if (action != null) ...[const SizedBox(height: 16), action!],
      ]),
    );
  }
}

/// Raíz: muestra la pantalla que corresponde al estado de la sesión
class AuthGate extends ConsumerWidget {
  const AuthGate({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return switch (ref.watch(sessionProvider)) {
      SessionLoading() => const Scaffold(body: Center(child: CircularProgressIndicator())),
      NeedsServer(:final error, :final current) => ServerScreen(error: error, current: current),
      UpdateRequired(:final minVersion, :final current) => UpdateRequiredScreen(minVersion: minVersion, current: current),
      LoggedOut(:final error, :final notice) => LoginScreen(key: ValueKey('$error$notice'), error: error, notice: notice),
      NeedsTwoFactor(:final error) => TwoFactorScreen(error: error),
      LoggedIn(:final me) => HomeShell(key: ValueKey('${me.id}-${me.currentCompanyId}'), me: me),
    };
  }
}
