import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/theme.dart';
import 'session.dart';

/// Marco centrado de las pantallas de acceso
class AuthCard extends StatelessWidget {
  const AuthCard({super.key, required this.title, this.subtitle, required this.children});

  final String title;
  final String? subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Card(
              color: NtColors.superficie,
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: const BorderSide(color: NtColors.borde)),
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text('NutriTrack', style: TextStyle(color: NtColors.marcaTexto, fontWeight: FontWeight.w700, fontSize: 15)),
                    const SizedBox(height: 6),
                    Text(title, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
                    if (subtitle != null) ...[
                      const SizedBox(height: 6),
                      Text(subtitle!, style: const TextStyle(color: NtColors.textoSuave)),
                    ],
                    const SizedBox(height: 20),
                    ...children,
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class NtBanner extends StatelessWidget {
  const NtBanner(this.message, {super.key, this.error = true});

  final String message;
  final bool error;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: error ? NtColors.errorSuave : NtColors.exitoSuave,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Semantics(
        liveRegion: true,
        child: Text(message, style: TextStyle(color: error ? NtColors.error : NtColors.exito, fontWeight: FontWeight.w600)),
      ),
    );
  }
}

/// Primera vez: dirección del servidor de NutriTrack de la institución
class ServerScreen extends ConsumerStatefulWidget {
  const ServerScreen({super.key, this.error, this.current});

  final String? error;
  final String? current;

  @override
  ConsumerState<ServerScreen> createState() => _ServerScreenState();
}

class _ServerScreenState extends ConsumerState<ServerScreen> {
  late final _url = TextEditingController(text: widget.current ?? '');
  bool _busy = false;

  Future<void> _save() async {
    if (_url.text.trim().isEmpty) return;
    setState(() => _busy = true);
    await ref.read(sessionProvider.notifier).configureServer(_url.text);
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    return AuthCard(
      title: 'Servidor',
      subtitle: 'Escribe la dirección de NutriTrack de tu institución, la misma que usas en el navegador.',
      children: [
        if (widget.error != null) NtBanner(widget.error!),
        TextField(
          controller: _url,
          autofocus: true,
          keyboardType: TextInputType.url,
          decoration: const InputDecoration(labelText: 'Dirección del servidor', hintText: 'https://nutritrack.hospital.org'),
          onSubmitted: (_) => _save(),
        ),
        const SizedBox(height: 16),
        FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Conectando…' : 'Conectar')),
      ],
    );
  }
}

class UpdateRequiredScreen extends StatelessWidget {
  const UpdateRequiredScreen({super.key, required this.minVersion, required this.current});

  final String minVersion;
  final String current;

  @override
  Widget build(BuildContext context) {
    return AuthCard(
      title: 'Actualiza la aplicación',
      subtitle: 'Tienes la versión $current y el servidor exige la $minVersion o posterior. Instala la versión nueva para continuar.',
      children: const [],
    );
  }
}

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key, this.error, this.notice});

  final String? error;
  final String? notice;

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;

  Future<void> _submit() async {
    if (_email.text.trim().isEmpty || _password.text.isEmpty) return;
    setState(() => _busy = true);
    await ref.read(sessionProvider.notifier).login(_email.text, _password.text);
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    return AuthCard(
      title: 'Iniciar sesión',
      subtitle: 'Usa tu correo y contraseña de NutriTrack.',
      children: [
        if (widget.error != null) NtBanner(widget.error!),
        if (widget.notice != null) NtBanner(widget.notice!, error: false),
        AutofillGroup(
          child: Column(children: [
            TextField(
              controller: _email,
              autofocus: true,
              keyboardType: TextInputType.emailAddress,
              autofillHints: const [AutofillHints.email],
              decoration: const InputDecoration(labelText: 'Correo electrónico'),
              textInputAction: TextInputAction.next,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _password,
              obscureText: true,
              autofillHints: const [AutofillHints.password],
              decoration: const InputDecoration(labelText: 'Contraseña'),
              onSubmitted: (_) => _submit(),
            ),
          ]),
        ),
        const SizedBox(height: 16),
        FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'Ingresando…' : 'Ingresar')),
        const SizedBox(height: 8),
        TextButton(
          onPressed: () => ref.read(sessionProvider.notifier).changeServer(),
          child: const Text('Cambiar servidor'),
        ),
      ],
    );
  }
}

class TwoFactorScreen extends ConsumerStatefulWidget {
  const TwoFactorScreen({super.key, this.error});

  final String? error;

  @override
  ConsumerState<TwoFactorScreen> createState() => _TwoFactorScreenState();
}

class _TwoFactorScreenState extends ConsumerState<TwoFactorScreen> {
  final _code = TextEditingController();
  bool _recovery = false;
  bool _busy = false;

  Future<void> _submit() async {
    if (_code.text.trim().isEmpty) return;
    setState(() => _busy = true);
    final session = ref.read(sessionProvider.notifier);
    _recovery ? await session.verifyTwoFactor(recoveryCode: _code.text) : await session.verifyTwoFactor(code: _code.text);
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    return AuthCard(
      title: 'Verificación en dos pasos',
      subtitle: _recovery
          ? 'Escribe uno de tus códigos de recuperación.'
          : 'Escribe el código de 6 dígitos de tu aplicación de autenticación.',
      children: [
        if (widget.error != null) NtBanner(widget.error!),
        TextField(
          controller: _code,
          autofocus: true,
          keyboardType: _recovery ? TextInputType.text : TextInputType.number,
          decoration: InputDecoration(labelText: _recovery ? 'Código de recuperación' : 'Código'),
          onSubmitted: (_) => _submit(),
        ),
        const SizedBox(height: 16),
        FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'Verificando…' : 'Verificar')),
        TextButton(
          onPressed: () => setState(() {
            _recovery = !_recovery;
            _code.clear();
          }),
          child: Text(_recovery ? 'Usar el código de la aplicación' : 'Usar un código de recuperación'),
        ),
        TextButton(onPressed: () => ref.read(sessionProvider.notifier).cancelTwoFactor(), child: const Text('Volver')),
      ],
    );
  }
}
