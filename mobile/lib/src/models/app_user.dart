/// The signed-in user, as returned by `GET /api/auth/me`.
class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.roles,
    required this.isStaff,
    this.phone,
    this.guestProfileId,
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final List<String> roles;
  final bool isStaff;
  final int? guestProfileId;

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: (json['id'] as num).toInt(),
      name: (json['name'] ?? '') as String,
      email: (json['email'] ?? '') as String,
      phone: json['phone'] as String?,
      roles: ((json['roles'] as List?) ?? const [])
          .map((role) => role.toString())
          .toList(growable: false),
      isStaff: (json['is_staff'] as bool?) ?? false,
      guestProfileId: (json['guest_profile_id'] as num?)?.toInt(),
    );
  }

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) {
      return '?';
    }
    if (parts.length == 1) {
      return parts.first.substring(0, 1).toUpperCase();
    }
    return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
        .toUpperCase();
  }
}
