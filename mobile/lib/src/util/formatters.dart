import 'package:intl/intl.dart';

/// Small formatting helpers shared by the screens.
class Fmt {
  Fmt._();

  static final DateFormat _iso = DateFormat('yyyy-MM-dd');
  static final DateFormat _pretty = DateFormat('EEE d MMM yyyy');

  /// `2026-06-01` — the format the API expects.
  static String isoDate(DateTime date) => _iso.format(date);

  /// `Mon 1 Jun 2026` — friendlier display.
  static String prettyDate(DateTime? date) =>
      date == null ? '—' : _pretty.format(date);

  static String money(double amount, {String currency = 'USD'}) {
    final format = NumberFormat.simpleCurrency(name: currency);
    return format.format(amount);
  }
}
