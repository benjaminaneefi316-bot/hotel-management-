import 'package:flutter/material.dart';

/// Azure Bay design tokens.
///
/// Mirrors the website stylesheet (`backend/public/assets/css/app.css`) so the
/// app and the web suite share one visual language: charcoal chrome, warm gold
/// accents, a sand canvas, pill buttons and Playfair Display headings.
class AppTheme {
  AppTheme._();

  // --- Palette (website custom properties) ---------------------------------
  static const Color charcoal = Color(0xFF1F2933); // --ab-charcoal
  static const Color slate = Color(0xFF323F4B); // --ab-slate
  static const Color sand = Color(0xFFF5F3EF); // --ab-sand
  static const Color ivory = Color(0xFFFAF8F4); // --ab-ivory
  static const Color gold = Color(0xFFC9A227); // --ab-gold
  static const Color goldSoft = Color(0xFFE0C76A); // --ab-gold-soft
  static const Color text = Color(0xFF2B2B2B); // --ab-text
  static const Color muted = Color(0xFF6B7280); // --ab-muted
  static const Color border = Color(0xFFE7E2D8); // --ab-border

  /// Soft badge colours (`.badge-soft-*` on the website).
  static const Color success = Color(0xFF1E7A3D);
  static const Color warning = Color(0xFF9A6B12);
  static const Color danger = Color(0xFFA12828);

  // --- Shape ---------------------------------------------------------------
  static const double radius = 14; // --ab-radius
  static const double radiusSm = 10; // --ab-radius-sm
  static const String displayFont = 'PlayfairDisplay';

  /// Website card shadow (`--ab-shadow`).
  static const List<BoxShadow> shadow = <BoxShadow>[
    BoxShadow(color: Color(0x14000000), blurRadius: 30, offset: Offset(0, 8)),
  ];

  /// Website large shadow (`--ab-shadow-lg`).
  static const List<BoxShadow> shadowLarge = <BoxShadow>[
    BoxShadow(color: Color(0x24000000), blurRadius: 50, offset: Offset(0, 18)),
  ];

  /// Serif heading style (`h1`-`h4`, `.kpi-value` on the website).
  static TextStyle display(
    double size, {
    FontWeight weight = FontWeight.w600,
    Color color = charcoal,
  }) {
    return TextStyle(
      fontFamily: displayFont,
      fontSize: size,
      fontWeight: weight,
      color: color,
      letterSpacing: 0.2,
      height: 1.15,
    );
  }

  /// Uppercase gold label (`.eyebrow` on the website).
  static const TextStyle eyebrow = TextStyle(
    fontSize: 10.5,
    fontWeight: FontWeight.w600,
    letterSpacing: 3,
    color: gold,
  );

  static ThemeData light() {
    final ColorScheme scheme = ColorScheme.fromSeed(
      seedColor: charcoal,
      brightness: Brightness.light,
    ).copyWith(
      primary: charcoal,
      onPrimary: Colors.white,
      secondary: gold,
      onSecondary: charcoal,
      surface: Colors.white,
      onSurface: text,
      outline: border,
    );

    final TextTheme textTheme = Typography.material2021().black.copyWith(
      displayLarge: display(40, weight: FontWeight.w700),
      displayMedium: display(34, weight: FontWeight.w700),
      displaySmall: display(28, weight: FontWeight.w700),
      headlineMedium: display(24),
      headlineSmall: display(21),
      titleLarge: display(20),
      titleMedium: display(17),
      titleSmall: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w600,
        color: text,
      ),
      bodyLarge: const TextStyle(fontSize: 15, height: 1.45, color: text),
      bodyMedium: const TextStyle(fontSize: 14, height: 1.45, color: text),
      bodySmall: const TextStyle(fontSize: 12.5, height: 1.4, color: muted),
      labelLarge: const TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w600,
        letterSpacing: 0.2,
      ),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      textTheme: textTheme,
      scaffoldBackgroundColor: sand,
      dividerColor: border,
      dividerTheme: const DividerThemeData(
        color: border,
        thickness: 1,
        space: 1,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: charcoal,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: display(19.5),
        shape: const Border(bottom: BorderSide(color: border)),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        indicatorColor: gold,
        elevation: 0,
        height: 68,
        iconTheme: WidgetStateProperty.resolveWith(
          (Set<WidgetState> states) => IconThemeData(
            size: 23,
            color: states.contains(WidgetState.selected) ? charcoal : muted,
          ),
        ),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (Set<WidgetState> states) => TextStyle(
            fontSize: 11.5,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w600
                : FontWeight.w500,
            color: states.contains(WidgetState.selected) ? charcoal : muted,
          ),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: charcoal,
          foregroundColor: Colors.white,
          disabledBackgroundColor: charcoal.withAlpha(70),
          disabledForegroundColor: Colors.white70,
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 15),
          shape: const StadiumBorder(),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            letterSpacing: 0.2,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: charcoal,
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 15),
          side: const BorderSide(color: border),
          shape: const StadiumBorder(),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            letterSpacing: 0.2,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: charcoal,
          shape: const StadiumBorder(),
          textStyle: const TextStyle(fontWeight: FontWeight.w600),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        labelStyle: const TextStyle(color: muted),
        floatingLabelStyle: const TextStyle(
          color: charcoal,
          fontWeight: FontWeight.w600,
        ),
        hintStyle: const TextStyle(color: Color(0xFF9AA2AB)),
        prefixIconColor: muted,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: _inputBorder(border),
        enabledBorder: _inputBorder(border),
        focusedBorder: _inputBorder(goldSoft, width: 1.6),
        errorBorder: _inputBorder(danger),
        focusedErrorBorder: _inputBorder(danger, width: 1.6),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: gold),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: charcoal,
        contentTextStyle: const TextStyle(color: Colors.white),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusSm),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radius),
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radius),
          side: const BorderSide(color: border),
        ),
      ),
      popupMenuTheme: PopupMenuThemeData(
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusSm),
        ),
      ),
      textSelectionTheme: const TextSelectionThemeData(
        cursorColor: charcoal,
        selectionColor: Color(0x33C9A227),
        selectionHandleColor: charcoal,
      ),
    );
  }

  static OutlineInputBorder _inputBorder(Color color, {double width = 1}) {
    return OutlineInputBorder(
      borderRadius: BorderRadius.circular(radiusSm),
      borderSide: BorderSide(color: color, width: width),
    );
  }

  /// Rounded white surface used for cards and panels (`.card` on the website).
  static BoxDecoration panel({Color color = Colors.white, bool elevated = true}) {
    return BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: border),
      boxShadow: elevated ? shadow : null,
    );
  }
}
