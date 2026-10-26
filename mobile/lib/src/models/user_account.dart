/// A user account as returned by the admin-only `/api/users` endpoints.
class UserAccount {
  const UserAccount({
    required this.id,
    required this.name,
    required this.username,
    required this.email,
    required this.status,
    required this.statusLabel,
    required this.roles,
    required this.roleLabels,
    required this.twoFactorEnabled,
    this.phone,
    this.lastLoginAt,
  });

  final int id;
  final String name;
  final String username;
  final String email;
  final String? phone;
  final String status;
  final String statusLabel;
  final List<String> roles;
  final List<String> roleLabels;
  final bool twoFactorEnabled;
  final DateTime? lastLoginAt;

  bool get isActive => status == 'active';
  bool get isAdmin => roles.contains('admin');

  factory UserAccount.fromJson(Map<String, dynamic> json) {
    return UserAccount(
      id: (json['id'] as num).toInt(),
      name: (json['name'] ?? '') as String,
      username: (json['username'] ?? '') as String,
      email: (json['email'] ?? '') as String,
      phone: json['phone'] as String?,
      status: (json['status'] ?? '') as String,
      statusLabel: (json['status_label'] ?? '') as String,
      roles: ((json['roles'] as List?) ?? const [])
          .map((role) => role.toString())
          .toList(growable: false),
      roleLabels: ((json['role_labels'] as List?) ?? const [])
          .map((role) => role.toString())
          .toList(growable: false),
      twoFactorEnabled: (json['two_factor_enabled'] as bool?) ?? false,
      lastLoginAt: DateTime.tryParse((json['last_login_at'] ?? '') as String? ?? ''),
    );
  }
}

/// One of the three roles a user can hold.
class RoleOption {
  const RoleOption({
    required this.name,
    required this.label,
    required this.description,
  });

  final String name;
  final String label;
  final String description;

  factory RoleOption.fromJson(Map<String, dynamic> json) {
    return RoleOption(
      name: (json['name'] ?? '') as String,
      label: (json['label'] ?? '') as String,
      description: (json['description'] ?? '') as String,
    );
  }
}
