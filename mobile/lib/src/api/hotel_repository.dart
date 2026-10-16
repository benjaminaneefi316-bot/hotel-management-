import '../api/api_client.dart';
import '../models/booking.dart';
import '../models/dashboard_summary.dart';
import '../models/room.dart';
import '../models/room_type.dart';

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

  /// Staff KPI summary.
  Future<DashboardSummary> dashboardSummary() async {
    final response = await _api.get('/dashboard/summary');
    return DashboardSummary.fromJson(response);
  }

  List<Map<String, dynamic>> _list(Map<String, dynamic> response) {
    return ((response['data'] as List?) ?? const [])
        .whereType<Map<String, dynamic>>()
        .toList(growable: false);
  }
}
