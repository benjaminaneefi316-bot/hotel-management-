/// A bookable room category from the public catalogue (`GET /api/room-types`).
class RoomType {
  const RoomType({
    required this.id,
    required this.name,
    required this.slug,
    required this.bedType,
    required this.capacity,
    required this.basePrice,
    required this.amenities,
    this.code,
    this.description,
    this.imageUrl,
  });

  final int id;
  final String name;
  final String slug;
  final String bedType;
  final int capacity;
  final double basePrice;
  final List<String> amenities;
  final String? code;
  final String? description;
  final String? imageUrl;

  factory RoomType.fromJson(Map<String, dynamic> json) {
    return RoomType(
      id: (json['id'] as num).toInt(),
      name: (json['name'] ?? '') as String,
      slug: (json['slug'] ?? '') as String,
      bedType: (json['bed_type'] ?? '') as String,
      capacity: (json['capacity'] as num?)?.toInt() ?? 2,
      basePrice: (json['base_price'] as num?)?.toDouble() ?? 0,
      amenities: ((json['amenities'] as List?) ?? const [])
          .map((item) => item.toString())
          .toList(growable: false),
      code: json['code'] as String?,
      description: json['description'] as String?,
      imageUrl: json['image_url'] as String?,
    );
  }
}
