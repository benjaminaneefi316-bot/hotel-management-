/// A payment recorded against a booking.
class Payment {
  const Payment({
    required this.id,
    required this.amount,
    required this.currency,
    required this.status,
    required this.statusLabel,
    this.methodLabel,
    this.paidAt,
  });

  final int id;
  final double amount;
  final String currency;
  final String status;
  final String statusLabel;
  final String? methodLabel;
  final DateTime? paidAt;

  factory Payment.fromJson(Map<String, dynamic> json) {
    return Payment(
      id: (json['id'] as num).toInt(),
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      currency: (json['currency'] ?? 'USD') as String,
      status: (json['status'] ?? '') as String,
      statusLabel: (json['status_label'] ?? '') as String,
      methodLabel: json['method_label'] as String?,
      paidAt: DateTime.tryParse((json['paid_at'] ?? '') as String),
    );
  }
}
