import 'package:intl/intl.dart';

/// Small formatting helpers shared by the screens.
class Fmt {
  Fmt._();

  static final DateFormat _iso = DateFormat('yyyy-MM-dd');
  static final DateFormat _pretty = DateFormat('EEE d MMM yyyy');
  static final DateFormat _stamp = DateFormat('d MMM yyyy, HH:mm');

  /// `2026-06-01` — the format the API expects.
  static String isoDate(DateTime date) => _iso.format(date);

  /// `Mon 1 Jun 2026` — friendlier display.
  static String prettyDate(DateTime? date) =>
      date == null ? '—' : _pretty.format(date);

  /// `1 Jun 2026, 14:05` - date and time for payment records.
  static String dateTime(DateTime? date) =>
      date == null ? '-' : _stamp.format(date);

  static String money(double amount, {String currency = 'USD'}) {
    final format = NumberFormat.simpleCurrency(name: currency);
    return format.format(amount);
  }
}
