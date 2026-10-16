/// KPI figures for the staff home screen (`GET /api/dashboard/summary`).
class DashboardSummary {
  const DashboardSummary({
    required this.date,
    required this.arrivalsToday,
    required this.departuresToday,
    required this.inHouse,
    required this.occupancyPercent,
    required this.revenueMonth,
    required this.pendingBookings,
    required this.rooms,
  });

  final String date;
  final int arrivalsToday;
  final int departuresToday;
  final int inHouse;
  final double occupancyPercent;
  final double revenueMonth;
  final int pendingBookings;
  final List<RoomStatusCount> rooms;

  factory DashboardSummary.fromJson(Map<String, dynamic> json) {
    final kpi = (json['kpi'] as Map<String, dynamic>?) ?? const {};

    return DashboardSummary(
      date: (json['date'] ?? '') as String,
      arrivalsToday: (kpi['arrivals_today'] as num?)?.toInt() ?? 0,
      departuresToday: (kpi['departures_today'] as num?)?.toInt() ?? 0,
      inHouse: (kpi['in_house'] as num?)?.toInt() ?? 0,
      occupancyPercent: (kpi['occupancy_percent'] as num?)?.toDouble() ?? 0,
      revenueMonth: (kpi['revenue_month'] as num?)?.toDouble() ?? 0,
      pendingBookings: (kpi['pending_bookings'] as num?)?.toInt() ?? 0,
      rooms: ((json['rooms'] as List?) ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(RoomStatusCount.fromJson)
          .toList(growable: false),
    );
  }
}

/// How many rooms are in a given housekeeping status.
class RoomStatusCount {
  const RoomStatusCount({
    required this.status,
    required this.label,
    required this.count,
  });

  final String status;
  final String label;
  final int count;

  factory RoomStatusCount.fromJson(Map<String, dynamic> json) {
    return RoomStatusCount(
      status: (json['status'] ?? '') as String,
      label: (json['label'] ?? '') as String,
      count: (json['count'] as num?)?.toInt() ?? 0,
    );
  }
}
