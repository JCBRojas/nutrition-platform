import 'package:flutter/material.dart';

/// Paleta NUT-CMP-001 (violeta, sin rosa), los mismos tokens de public/assets/css/theme.css.
/// Botones con los colores de Bootstrap (§2b) y etiquetas del catálogo de dietas con sus
/// colores originales (§2c).
class NtColors {
  static const primario = Color(0xFF6C5DD3);
  static const primarioHover = Color(0xFF5A4BC2);
  static const marcaTexto = Color(0xFF5B4CC4);
  static const primarioSuave = Color(0xFFE8E5FB);
  static const primarioSuaveTexto = Color(0xFF4A3BB0);
  static const fondo = Color(0xFFEEF0FA);
  static const superficie = Color(0xFFFFFFFF);
  static const borde = Color(0xFFDCDFF0);
  static const texto = Color(0xFF1F2340);
  static const textoSuave = Color(0xFF5E6384);
  static const exito = Color(0xFF166534);
  static const exitoSuave = Color(0xFFE3F4EA);
  static const error = Color(0xFFB91C1C);
  static const errorSuave = Color(0xFFFDECEC);
  static const advertencia = Color(0xFF92400E);
  static const advertenciaSuave = Color(0xFFFEF3E2);

  // Botones (Bootstrap 5.3)
  static const btnPrimary = Color(0xFF0D6EFD);
  static const btnSuccess = Color(0xFF198754);
  static const btnDanger = Color(0xFFDC3545);
  static const btnSecondary = Color(0xFF6C757D);

  // Catálogo de dietas
  static const dietaTiempo = Color(0xFF4A7FCB);
  static const dietaTiempoSuave = Color(0xFFDDEAF8);
  static const dietaConsistencia = Color(0xFF2D6A4F);
  static const dietaConsistenciaSuave = Color(0xFFD8F3DC);
  static const dietaEspecificacion = Color(0xFF7B5EA7);
  static const dietaEspecificacionSuave = Color(0xFFEDE9F5);
  static const dietaEntregado = Color(0xFF0F766E);
}

ThemeData buildTheme() {
  final scheme = ColorScheme.fromSeed(
    seedColor: NtColors.primario,
    primary: NtColors.primario,
    onPrimary: Colors.white,
    surface: NtColors.superficie,
    onSurface: NtColors.texto,
    error: NtColors.error,
  );

  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: NtColors.fondo,
    dividerColor: NtColors.borde,
    appBarTheme: const AppBarTheme(
      backgroundColor: NtColors.superficie,
      foregroundColor: NtColors.texto,
      elevation: 0,
      scrolledUnderElevation: 1,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: NtColors.btnPrimary,
        foregroundColor: Colors.white,
        minimumSize: const Size(0, 44),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: NtColors.texto,
        minimumSize: const Size(0, 44),
        side: const BorderSide(color: NtColors.btnSecondary),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: NtColors.superficie,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: NtColors.borde)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: NtColors.borde)),
    ),
    navigationRailTheme: const NavigationRailThemeData(
      backgroundColor: NtColors.superficie,
      indicatorColor: NtColors.primarioSuave,
      selectedIconTheme: IconThemeData(color: NtColors.primarioSuaveTexto),
      selectedLabelTextStyle: TextStyle(color: NtColors.primarioSuaveTexto, fontWeight: FontWeight.w600),
      unselectedLabelTextStyle: TextStyle(color: NtColors.textoSuave),
    ),
  );
}
