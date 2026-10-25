import 'package:flutter/widgets.dart';

/// Tamaño mínimo de la ventana. Lo aplican el código nativo (macos/Runner/MainFlutterWindow.swift
/// y windows/runner/win32_window.h, mismo valor) y las pruebas verifican que las pantallas
/// caben en él sin desbordarse.
const minWindowSize = Size(1100, 700);
