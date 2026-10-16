import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../api/api_client.dart';
import '../models/app_user.dart';

/// Result of step one of authentication.
class LoginResult {
  const LoginResult({
    required this.requiresTwoFactor,
    this.challengeToken,
    this.email,
    this.devCode,
  });

  /// When false the account has 2FA disabled and is already signed in.
  final bool requiresTwoFactor;
  final String? challengeToken;
  final String? email;

  /// Only populated by the backend when `TWO_FACTOR_EXPOSE_CODE=true`
  /// (local development / demo). Never present in production.
  final String? devCode;
}

/// Wraps the `/auth/*` endpoints and persists the bearer token securely.
class AuthRepository {
  AuthRepository({required ApiClient api, FlutterSecureStorage? storage})
      : _api = api,
        _storage = storage ?? const FlutterSecureStorage();

  static const String _tokenKey = 'azure_bay.bearer_token';

  final ApiClient _api;
  final FlutterSecureStorage _storage;

  /// Load any stored token onto the client and return it.
  Future<String?> bootstrap() async {
    final token = await _storage.read(key: _tokenKey);
    _api.setToken(token);
    return token;
  }

  /// Step 1: verify the password. May complete the login outright (2FA off)
  /// or return a challenge that must be verified with an emailed code.
  Future<LoginResult> login(String email, String password) async {
    final response = await _api.post('/auth/login', body: {
      'email': email,
      'password': password,
    });

    if (response['status'] == 'authenticated') {
      await _persistToken(response['token'] as String?);
      return const LoginResult(requiresTwoFactor: false);
    }

    return LoginResult(
      requiresTwoFactor: true,
      challengeToken: response['challenge_token'] as String?,
      email: response['email'] as String?,
      devCode: response['dev_code'] as String?,
    );
  }

  /// Step 2: exchange the emailed one-time code for a bearer token.
  Future<void> verifyTwoFactor(String challengeToken, String code) async {
    final response = await _api.post('/auth/two-factor', body: {
      'challenge_token': challengeToken,
      'code': code,
    });

    await _persistToken(response['token'] as String?);
  }

  /// Ask the server to email a fresh code.
  Future<String?> resend(String challengeToken) async {
    final response = await _api.post('/auth/resend', body: {
      'challenge_token': challengeToken,
    });

    return response['dev_code'] as String?;
  }

  /// The signed-in user's profile.
  Future<AppUser> me() async {
    final response = await _api.get('/auth/me');
    return AppUser.fromJson(response['user'] as Map<String, dynamic>);
  }

  /// Revoke the current token and forget it locally.
  Future<void> signOut() async {
    try {
      await _api.post('/auth/logout');
    } catch (_) {
      // Even if the server call fails, drop the local token.
    }
    await _storage.delete(key: _tokenKey);
    _api.setToken(null);
  }

  Future<void> _persistToken(String? token) async {
    if (token == null || token.isEmpty) {
      return;
    }
    _api.setToken(token);
    await _storage.write(key: _tokenKey, value: token);
  }
}
