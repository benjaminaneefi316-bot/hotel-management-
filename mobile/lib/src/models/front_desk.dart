import 'booking.dart';

/// Today's arrivals and departures, as returned by `/api/front-desk`.
class FrontDeskDay {
  const FrontDeskDay({
    required this.date,
    required this.arrivals,
    required this.departures,
  });

  final String date;
  final List<Booking> arrivals;
  final List<Booking> departures;

  bool get isEmpty => arrivals.isEmpty && departures.isEmpty;

  factory FrontDeskDay.fromJson(Map<String, dynamic> json) {
    return FrontDeskDay(
      date: (json['date'] ?? '') as String,
      arrivals: _bookings(json['arrivals']),
      departures: _bookings(json['departures']),
    );
  }

  static List<Booking> _bookings(dynamic raw) {
    return ((raw as List?) ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(Booking.fromJson)
        .toList(growable: false);
  }
}
