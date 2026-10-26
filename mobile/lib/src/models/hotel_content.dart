import 'room_type.dart';

/// The public marketing content of the hotel, mirrored from the website.
class HotelContent {
  const HotelContent({
    required this.hotel,
    required this.stats,
    required this.amenities,
    required this.gallery,
    required this.highlights,
    required this.roomTypes,
    required this.featuredRoomTypes,
  });

  final HotelInfo hotel;
  final List<HotelStat> stats;
  final List<HotelAmenity> amenities;
  final List<HotelGalleryImage> gallery;
  final List<HotelHighlight> highlights;
  final List<RoomType> roomTypes;
  final List<RoomType> featuredRoomTypes;

  factory HotelContent.fromJson(Map<String, dynamic> json) {
    List<T> parse<T>(String key, T Function(Map<String, dynamic>) build) {
      return ((json[key] as List?) ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(build)
          .toList(growable: false);
    }

    return HotelContent(
      hotel: HotelInfo.fromJson((json['hotel'] as Map<String, dynamic>?) ?? const {}),
      stats: parse('stats', HotelStat.fromJson),
      amenities: parse('amenities', HotelAmenity.fromJson),
      gallery: parse('gallery', HotelGalleryImage.fromJson),
      highlights: parse('highlights', HotelHighlight.fromJson),
      roomTypes: parse('room_types', RoomType.fromJson),
      featuredRoomTypes: parse('featured_room_types', RoomType.fromJson),
    );
  }
}

class HotelInfo {
  const HotelInfo({
    required this.name,
    required this.tagline,
    required this.description,
    required this.shortDescription,
    required this.address,
    required this.phone,
    required this.email,
    required this.checkInTime,
    required this.checkOutTime,
    required this.heroImage,
    required this.rating,
    required this.established,
  });

  final String name;
  final String tagline;
  final String description;
  final String shortDescription;
  final String address;
  final String phone;
  final String email;
  final String checkInTime;
  final String checkOutTime;
  final String heroImage;
  final double rating;
  final int established;

  factory HotelInfo.fromJson(Map<String, dynamic> json) {
    return HotelInfo(
      name: (json['name'] ?? 'Azure Bay Hotel') as String,
      tagline: (json['tagline'] ?? '') as String,
      description: (json['description'] ?? '') as String,
      shortDescription: (json['short_description'] ?? '') as String,
      address: (json['address'] ?? '') as String,
      phone: (json['phone'] ?? '') as String,
      email: (json['email'] ?? '') as String,
      checkInTime: (json['check_in_time'] ?? '') as String,
      checkOutTime: (json['check_out_time'] ?? '') as String,
      heroImage: (json['hero_image'] ?? '') as String,
      rating: (json['rating'] as num?)?.toDouble() ?? 0,
      established: (json['established'] as num?)?.toInt() ?? 0,
    );
  }
}

class HotelStat {
  const HotelStat({required this.value, required this.label});

  final String value;
  final String label;

  factory HotelStat.fromJson(Map<String, dynamic> json) => HotelStat(
        value: (json['value'] ?? '') as String,
        label: (json['label'] ?? '') as String,
      );
}

class HotelAmenity {
  const HotelAmenity({
    required this.icon,
    required this.title,
    required this.description,
  });

  final String icon;
  final String title;
  final String description;

  factory HotelAmenity.fromJson(Map<String, dynamic> json) => HotelAmenity(
        icon: (json['icon'] ?? '') as String,
        title: (json['title'] ?? '') as String,
        description: (json['description'] ?? '') as String,
      );
}

class HotelGalleryImage {
  const HotelGalleryImage({required this.url, required this.caption});

  final String url;
  final String caption;

  factory HotelGalleryImage.fromJson(Map<String, dynamic> json) =>
      HotelGalleryImage(
        url: (json['url'] ?? '') as String,
        caption: (json['caption'] ?? '') as String,
      );
}

class HotelHighlight {
  const HotelHighlight({
    required this.title,
    required this.body,
    required this.image,
  });

  final String title;
  final String body;
  final String image;

  factory HotelHighlight.fromJson(Map<String, dynamic> json) => HotelHighlight(
        title: (json['title'] ?? '') as String,
        body: (json['body'] ?? '') as String,
        image: (json['image'] ?? '') as String,
      );
}
