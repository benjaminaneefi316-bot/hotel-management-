import 'package:flutter/foundation.dart';

import '../api/api_exception.dart';
import '../auth/auth_repository.dart';
import '../models/app_user.dart';

/// Holds the signed-in user for the whole app and exposes the sign-in flow.
class SessionController extends ChangeNotifier {
  SessionController(this._auth);

  final AuthRepository _auth;

  AppUser? _user;
  bool _busy = false;
  String? _error;

  AppUser? get user => _user;
  bool get isSignedIn => _user != null;
  bool get isStaff => _user?.isStaff ?? false;
  bool get busy => _busy;
  String? get error => _error;

  /// Restore a previous session (called once at start-up).
  Future<bool> restore() async {
    final token = await _auth.bootstrap();
    if (token == null) {
      return false;
    }

    try {
      _user = await _auth.me();
      notifyListeners();
      return true;
    } on ApiException {
      await _auth.signOut();
      return false;
    }
  }

  /// Step 1 of sign-in. Returns the challenge when a second factor is needed.
  Future<LoginResult?> signIn(String email, String password) async {
    _error = null;
    _busy = true;
    notifyListeners();

    try {
      final result = await _auth.login(email, password);
      if (!result.requiresTwoFactor) {
        _user = await _auth.me();
      }
      return result;
    } on ApiException catch (e) {
      _error = e.message;
      return null;
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// Step 2 of sign-in.
  Future<bool> verifyCode(String challengeToken, String code) async {
    _error = null;
    _busy = true;
    notifyListeners();

    try {
      await _auth.verifyTwoFactor(challengeToken, code);
      _user = await _auth.me();
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      return false;
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  Future<String?> resendCode(String challengeToken) async {
    try {
      return await _auth.resend(challengeToken);
    } on ApiException catch (e) {
      _error = e.message;
      notifyListeners();
      return null;
    }
  }

  Future<void> signOut() async {
    _busy = true;
    notifyListeners();
    await _auth.signOut();
    _user = null;
    _busy = false;
    notifyListeners();
  }
}
