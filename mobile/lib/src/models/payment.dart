/// A payment record with the booking context the payments screen needs.
class Payment {
  const Payment({
    required this.id,
    required this.amount,
    required this.currency,
    required this.status,
    required this.statusLabel,
    this.bookingId,
    this.bookingReference,
    this.guestName,
    this.method,
    this.methodLabel,
    this.notes,
    this.providerReference,
    this.recordedBy,
    this.paidAt,
    this.refundedAt,
  });

  final int id;
  final double amount;
  final String currency;
  final String status;
  final String statusLabel;
  final int? bookingId;
  final String? bookingReference;
  final String? guestName;
  final String? method;
  final String? methodLabel;
  final String? notes;
  final String? providerReference;
  final String? recordedBy;
  final DateTime? paidAt;
  final DateTime? refundedAt;

  bool get isPaid => status == 'paid';
  bool get isRefunded => status == 'refunded';

  factory Payment.fromJson(Map<String, dynamic> json) {
    return Payment(
      id: (json['id'] as num).toInt(),
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      currency: (json['currency'] ?? 'USD') as String,
      status: (json['status'] ?? '') as String,
      statusLabel: (json['status_label'] ?? '') as String,
      bookingId: (json['booking_id'] as num?)?.toInt(),
      bookingReference: json['booking_reference'] as String?,
      guestName: json['guest_name'] as String?,
      method: json['method'] as String?,
      methodLabel: json['method_label'] as String?,
      notes: json['notes'] as String?,
      providerReference: json['provider_reference'] as String?,
      recordedBy: json['recorded_by'] as String?,
      paidAt: DateTime.tryParse((json['paid_at'] ?? '') as String? ?? ''),
      refundedAt:
          DateTime.tryParse((json['refunded_at'] ?? '') as String? ?? ''),
    );
  }
}

/// Header totals for the payments screen.
class PaymentSummary {
  const PaymentSummary({
    required this.paidTotal,
    required this.pendingTotal,
    required this.refundedTotal,
    required this.currency,
    required this.paidCount,
    required this.pendingCount,
  });

  final double paidTotal;
  final double pendingTotal;
  final double refundedTotal;
  final String currency;
  final int paidCount;
  final int pendingCount;

  factory PaymentSummary.fromJson(Map<String, dynamic> json) {
    final counts = (json['counts'] as Map?) ?? const {};
    return PaymentSummary(
      paidTotal: (json['paid_total'] as num?)?.toDouble() ?? 0,
      pendingTotal: (json['pending_total'] as num?)?.toDouble() ?? 0,
      refundedTotal: (json['refunded_total'] as num?)?.toDouble() ?? 0,
      currency: (json['currency'] ?? 'USD') as String,
      paidCount: (counts['paid'] as num?)?.toInt() ?? 0,
      pendingCount: (counts['pending'] as num?)?.toInt() ?? 0,
    );
  }
}
