/// A problem talking to the hotel API.
///
/// The REST API answers errors as `{ "message": "...", "reason": "..." }`, so
/// [reason] carries the machine-readable code (for example `no_availability`)
/// while [message] is safe to show to the user.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.reason});

  final String message;
  final int? statusCode;
  final String? reason;

  /// True when the user must sign in again (missing or expired token).
  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => message;
}
