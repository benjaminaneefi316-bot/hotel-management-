/// A physical room, from `GET /api/rooms` (staff) or `/api/availability`.
class Room {
  const Room({
    required this.id,
    required this.roomNumber,
    required this.status,
    required this.statusLabel,
    required this.effectiveRate,
    this.floor,
    this.roomTypeName,
    this.capacity,
    this.bedType,
    this.imageUrl,
  });

  final int id;
  final String roomNumber;
  final String status;
  final String statusLabel;
  final double effectiveRate;
  final int? floor;
  final String? roomTypeName;
  final int? capacity;
  final String? bedType;
  final String? imageUrl;

  factory Room.fromJson(Map<String, dynamic> json) {
    final type = json['room_type'];
    final typeMap = type is Map<String, dynamic> ? type : null;

    return Room(
      id: (json['id'] as num).toInt(),
      roomNumber: (json['room_number'] ?? '') as String,
      status: (json['status'] ?? '') as String,
      statusLabel: (json['status_label'] ?? '') as String,
      effectiveRate: (json['effective_rate'] as num?)?.toDouble() ?? 0,
      floor: (json['floor'] as num?)?.toInt(),
      roomTypeName: typeMap?['name'] as String?,
      capacity: (typeMap?['capacity'] as num?)?.toInt(),
      bedType: typeMap?['bed_type'] as String?,
      imageUrl: typeMap?['image_url'] as String?,
    );
  }
}
