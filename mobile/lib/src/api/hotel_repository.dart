import '../api/api_client.dart';
import '../models/booking.dart';
import '../models/dashboard_summary.dart';
import '../models/front_desk.dart';
import '../models/hotel_content.dart';
import '../models/payment.dart';
import '../models/room.dart';
import '../models/room_type.dart';
import '../models/two_factor.dart';
import '../models/user_account.dart';

/// Read/write access to the hotel resources exposed by the REST API.
class HotelRepository {
  HotelRepository(this._api);

  final ApiClient _api;

  /// The public room-type catalogue.
  Future<List<RoomType>> roomTypes() async {
    final response = await _api.get('/room-types');
    return _list(response).map(RoomType.fromJson).toList(growable: false);
  }

  /// Rooms free for the given `yyyy-MM-dd` window.
  Future<List<Room>> availability({
    required String checkInDate,
    required String checkOutDate,
    int? roomTypeId,
  }) async {
    final response = await _api.get('/availability', query: {
      'check_in_date': checkInDate,
      'check_out_date': checkOutDate,
      if (roomTypeId != null) 'room_type_id': roomTypeId,
    });
    return _list(response).map(Room.fromJson).toList(growable: false);
  }

  /// All rooms (staff only).
  Future<List<Room>> rooms({String? status}) async {
    final response = await _api.get('/rooms', query: {
      if (status != null && status.isNotEmpty) 'status': status,
    });
    return _list(response).map(Room.fromJson).toList(growable: false);
  }

  /// The caller's bookings (all bookings for staff, own bookings for a guest).
  Future<List<Booking>> bookings({String? status}) async {
    final response = await _api.get('/bookings', query: {
      if (status != null && status.isNotEmpty) 'status': status,
    });
    return _list(response).map(Booking.fromJson).toList(growable: false);
  }

  Future<Booking> booking(int id) async {
    final response = await _api.get('/bookings/$id');
    return Booking.fromJson(response['data'] as Map<String, dynamic>);
  }

  /// Guest self-service booking: pick a room type and let the hotel assign a room.
  Future<Booking> bookRoomType({
    required int roomTypeId,
    required String checkInDate,
    required String checkOutDate,
    required int adults,
    int children = 0,
    String? specialRequests,
  }) async {
    final response = await _api.post('/bookings', body: {
      'room_type_id': roomTypeId,
      'check_in_date': checkInDate,
      'check_out_date': checkOutDate,
      'adults': adults,
      'children': children,
      if (specialRequests != null && specialRequests.isNotEmpty)
        'special_requests': specialRequests,
    });
    return Booking.fromJson(response['data'] as Map<String, dynamic>);
  }

  /// Staff booking against an existing guest and specific rooms.
  Future<Booking> bookForGuest({
    required int guestId,
    required List<int> roomIds,
    required String checkInDate,
    required String checkOutDate,
    required int adults,
    int children = 0,
    String? specialRequests,
  }) async {
    final response = await _api.post('/bookings', body: {
      'guest_id': guestId,
      'room_ids': roomIds,
      'check_in_date': checkInDate,
      'check_out_date': checkOutDate,
      'adults': adults,
      'children': children,
      if (specialRequests != null && specialRequests.isNotEmpty)
        'special_requests': specialRequests,
    });
    return Booking.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<void> cancelBooking(int id, {String? reason}) async {
    await _api.post('/bookings/$id/cancel', body: {
      if (reason != null && reason.isNotEmpty) 'reason': reason,
    });
  }

  /// Confirm a pending booking so it can be checked in.
  Future<Booking> confirmBooking(int id) async {
    final response = await _api.post('/bookings/$id/confirm');
    return Booking.fromJson(response['data'] as Map<String, dynamic>);
  }

  /// Today's arrivals and departures (front desk).
  Future<FrontDeskDay> frontDesk() async {
    final response = await _api.get('/front-desk');
    return FrontDeskDay.fromJson(response);
  }

  /// Check a guest in. The API assigns the booking's first room.
  Future<void> checkIn(int bookingId) async {
    await _api.post('/bookings/$bookingId/check-in');
  }

  /// Check a guest out of an active stay.
  Future<void> checkOut(int stayId) async {
    await _api.post('/stays/$stayId/check-out');
  }

  /// Staff KPI summary.
  Future<DashboardSummary> dashboardSummary() async {
    final response = await _api.get('/dashboard/summary');
    return DashboardSummary.fromJson(response);
  }

  // --- Public hotel website ------------------------------------------------

  /// Marketing content for the hotel (hero, amenities, gallery, rooms).
  Future<HotelContent> hotelContent() async {
    final response = await _api.get('/hotel');
    return HotelContent.fromJson(response);
  }

  /// Send a contact enquiry. Returns the server's confirmation message.
  Future<String> sendContactEnquiry({
    required String name,
    required String email,
    required String message,
    String? phone,
    String? subject,
  }) async {
    final response = await _api.post('/hotel/contact', body: {
      'name': name,
      'email': email,
      'message': message,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      if (subject != null && subject.isNotEmpty) 'subject': subject,
    });
    return (response['message'] ?? 'Thank you. Our team will be in touch.') as String;
  }

  // --- Authenticator app (2FA) ---------------------------------------------

  Future<TwoFactorStatus> twoFactorStatus() async {
    final response = await _api.get('/two-factor');
    return TwoFactorStatus.fromJson(response);
  }

  /// Step 1: create or re-use a pending secret. Nothing is saved yet.
  Future<TwoFactorSetup> startTwoFactorSetup() async {
    final response = await _api.post('/two-factor/setup');
    return TwoFactorSetup.fromJson(response);
  }

  /// Step 2: prove a live code and switch the account over.
  Future<TwoFactorStatus> confirmTwoFactor(String code) async {
    final response = await _api.post('/two-factor/confirm', body: {'code': code});
    return TwoFactorStatus.fromJson(response);
  }

  /// Step 3: remove the authenticator; emailed codes are used again.
  Future<TwoFactorStatus> disableTwoFactor(String code) async {
    final response = await _api.delete('/two-factor', body: {'code': code});
    return TwoFactorStatus.fromJson(response);
  }

  // --- User administration (admins only) -----------------------------------

  Future<List<UserAccount>> users({
    String? search,
    String? role,
    String? status,
  }) async {
    final response = await _api.get('/users', query: {
      if (search != null && search.isNotEmpty) 'search': search,
      if (role != null && role.isNotEmpty) 'role': role,
      if (status != null && status.isNotEmpty) 'status': status,
      'per_page': 100,
    });
    return _list(response).map(UserAccount.fromJson).toList(growable: false);
  }

  Future<List<RoleOption>> roleOptions() async {
    final response = await _api.get('/users/roles');
    return _list(response).map(RoleOption.fromJson).toList(growable: false);
  }

  Future<UserAccount> createUser({
    required String name,
    required String username,
    required String email,
    required String password,
    required String passwordConfirmation,
    required List<String> roles,
    String status = 'active',
    String? phone,
  }) async {
    final response = await _api.post('/users', body: {
      'name': name,
      'username': username,
      'email': email,
      'phone': phone,
      'status': status,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'roles': roles,
    });
    return UserAccount.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<UserAccount> updateUser({
    required int id,
    required String name,
    required String username,
    required String email,
    required String status,
    List<String>? roles,
    String? phone,
  }) async {
    final response = await _api.put('/users/$id', body: {
      'name': name,
      'username': username,
      'email': email,
      'phone': phone,
      'status': status,
    });
    var user = UserAccount.fromJson(response['data'] as Map<String, dynamic>);

    if (roles != null && roles.isNotEmpty) {
      final rolesResponse =
          await _api.put('/users/$id/roles', body: {'roles': roles});
      user = UserAccount.fromJson(
        rolesResponse['data'] as Map<String, dynamic>,
      );
    }

    return user;
  }

  Future<UserAccount> activateUser(int id) async {
    final response = await _api.post('/users/$id/activate');
    return UserAccount.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<UserAccount> deactivateUser(int id) async {
    final response = await _api.post('/users/$id/deactivate');
    return UserAccount.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<void> resetUserPassword({
    required int id,
    required String password,
    required String confirmation,
  }) async {
    await _api.post('/users/$id/password', body: {
      'password': password,
      'password_confirmation': confirmation,
    });
  }

  // --- Payments ------------------------------------------------------------

  Future<List<Payment>> payments({
    int? bookingId,
    String? status,
    String? search,
  }) async {
    final response = await _api.get('/payments', query: {
      if (bookingId != null) 'booking_id': bookingId,
      if (status != null && status.isNotEmpty) 'status': status,
      if (search != null && search.isNotEmpty) 'search': search,
      'per_page': 100,
    });
    return _list(response).map(Payment.fromJson).toList(growable: false);
  }

  Future<PaymentSummary> paymentSummary() async {
    final response = await _api.get('/payments/summary');
    return PaymentSummary.fromJson(response);
  }

  Future<Payment> refundPayment(int id, {String? reason}) async {
    final response = await _api.post('/payments/$id/refund', body: {
      if (reason != null && reason.isNotEmpty) 'reason': reason,
    });
    return Payment.fromJson(response['data'] as Map<String, dynamic>);
  }

  /// Record a payment against a booking. No card data is ever sent.
  Future<Payment> recordPayment({
    required int bookingId,
    required double amount,
    required String method,
    String status = 'paid',
    String? providerReference,
    String? notes,
  }) async {
    final response = await _api.post('/payments', body: {
      'booking_id': bookingId,
      'amount': amount,
      'method': method,
      'status': status,
      if (providerReference != null && providerReference.isNotEmpty)
        'provider_reference': providerReference,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    });
    return Payment.fromJson(response['data'] as Map<String, dynamic>);
  }

  List<Map<String, dynamic>> _list(Map<String, dynamic> response) {
    return ((response['data'] as List?) ?? const [])
        .whereType<Map<String, dynamic>>()
        .toList(growable: false);
  }
}
