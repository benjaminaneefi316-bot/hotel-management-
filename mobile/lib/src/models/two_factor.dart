/// Authenticator-app state for the signed-in user.
class TwoFactorStatus {
  const TwoFactorStatus({
    required this.enabled,
    required this.channel,
    this.confirmedAt,
  });

  final bool enabled;

  /// `app` when an authenticator is enrolled, `email` otherwise.
  final String channel;
  final DateTime? confirmedAt;

  factory TwoFactorStatus.fromJson(Map<String, dynamic> json) {
    return TwoFactorStatus(
      enabled: (json['enabled'] as bool?) ?? false,
      channel: (json['channel'] ?? 'email') as String,
      confirmedAt:
          DateTime.tryParse((json['confirmed_at'] ?? '') as String? ?? ''),
    );
  }
}

/// A pending authenticator enrolment: the shared secret plus ready-made text
/// for the user to type into their authenticator app.
class TwoFactorSetup {
  const TwoFactorSetup({
    required this.secret,
    required this.otpauthUri,
    required this.issuer,
    required this.account,
    required this.expiresIn,
  });

  final String secret;
  final String otpauthUri;
  final String issuer;
  final String account;
  final int expiresIn;

  /// The secret in readable groups of four (`abcd efgh ...`).
  String get groupedSecret {
    final buffer = StringBuffer();
    for (var i = 0; i < secret.length; i++) {
      if (i > 0 && i % 4 == 0) {
        buffer.write(' ');
      }
      buffer.write(secret[i]);
    }
    return buffer.toString();
  }

  factory TwoFactorSetup.fromJson(Map<String, dynamic> json) {
    return TwoFactorSetup(
      secret: (json['secret'] ?? '') as String,
      otpauthUri: (json['otpauth_uri'] ?? '') as String,
      issuer: (json['issuer'] ?? 'Azure Bay Hotel') as String,
      account: (json['account'] ?? '') as String,
      expiresIn: (json['expires_in'] as num?)?.toInt() ?? 900,
    );
  }
}
