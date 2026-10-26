import 'payment.dart';

/// One room line on a booking.
class BookingRoom {
  const BookingRoom({
    required this.id,
    required this.roomNumber,
    required this.rate,
    this.roomTypeName,
  });

  final int id;
  final String roomNumber;
  final double rate;
  final String? roomTypeName;

  factory BookingRoom.fromJson(Map<String, dynamic> json) {
    return BookingRoom(
      id: (json['id'] as num).toInt(),
      roomNumber: (json['room_number'] ?? '') as String,
      rate: (json['rate'] as num?)?.toDouble() ?? 0,
      roomTypeName: json['room_type'] as String?,
    );
  }
}

/// The guest a booking belongs to (as exposed by the API).
class BookingGuest {
  const BookingGuest({
    required this.fullName,
    this.email,
    this.phone,
    this.nationality,
    this.country,
  });

  final String fullName;
  final String? email;
  final String? phone;
  final String? nationality;
  final String? country;

  factory BookingGuest.fromJson(Map<String, dynamic> json) {
    return BookingGuest(
      fullName: (json['full_name'] ?? '') as String,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      nationality: json['nationality'] as String?,
      country: json['country'] as String?,
    );
  }
}

/// A reservation, from the `/api/bookings` endpoints.
class Booking {
  const Booking({
    required this.id,
    required this.reference,
    required this.status,
    required this.statusLabel,
    required this.nights,
    required this.adults,
    required this.children,
    required this.totalAmount,
    required this.paidAmount,
    required this.balance,
    required this.canCancel,
    required this.canCheckIn,
    required this.canConfirm,
    required this.rooms,
    required this.payments,
    this.checkInDate,
    this.checkOutDate,
    this.specialRequests,
    this.guest,
    this.stayId,
  });

  final int id;
  final String reference;
  final String status;
  final String statusLabel;
  final int nights;
  final int adults;
  final int children;
  final double totalAmount;
  final double paidAmount;
  final double balance;
  final bool canCancel;
  final bool canCheckIn;
  final bool canConfirm;
  final List<BookingRoom> rooms;
  final List<Payment> payments;
  final DateTime? checkInDate;
  final DateTime? checkOutDate;
  final String? specialRequests;
  final BookingGuest? guest;

  /// The active stay, when the guest is already checked in (front desk).
  final int? stayId;

  factory Booking.fromJson(Map<String, dynamic> json) {
    return Booking(
      id: (json['id'] as num).toInt(),
      reference: (json['reference'] ?? '') as String,
      status: (json['status'] ?? '') as String,
      statusLabel: (json['status_label'] ?? '') as String,
      nights: (json['nights'] as num?)?.toInt() ?? 0,
      adults: (json['adults'] as num?)?.toInt() ?? 1,
      children: (json['children'] as num?)?.toInt() ?? 0,
      totalAmount: (json['total_amount'] as num?)?.toDouble() ?? 0,
      paidAmount: (json['paid_amount'] as num?)?.toDouble() ?? 0,
      balance: (json['balance'] as num?)?.toDouble() ?? 0,
      canCancel: (json['can_cancel'] as bool?) ?? false,
      canCheckIn: (json['can_check_in'] as bool?) ?? false,
      canConfirm: (json['can_confirm'] as bool?) ?? false,
      rooms: ((json['rooms'] as List?) ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(BookingRoom.fromJson)
          .toList(growable: false),
      payments: ((json['payments'] as List?) ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(Payment.fromJson)
          .toList(growable: false),
      checkInDate: DateTime.tryParse((json['check_in_date'] ?? '') as String),
      checkOutDate: DateTime.tryParse((json['check_out_date'] ?? '') as String),
      specialRequests: json['special_requests'] as String?,
      guest: json['guest'] is Map<String, dynamic>
          ? BookingGuest.fromJson(json['guest'] as Map<String, dynamic>)
          : null,
      stayId: (json['stay_id'] as num?)?.toInt(),
    );
  }
}
