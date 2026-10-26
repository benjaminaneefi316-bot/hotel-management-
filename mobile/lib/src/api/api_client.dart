import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_exception.dart';

/// A very small JSON client for the hotel REST API.
///
/// It centralises the base URL, the bearer token, JSON encoding/decoding and
/// turning non-2xx responses into [ApiException]s with the server's message.
///
/// The base URL is compiled in with `--dart-define`, for example:
///
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
///
/// `10.0.2.2` is how the Android emulator reaches the host machine's
/// `127.0.0.1`. A physical device needs the computer's LAN IP instead.
class ApiClient {
  ApiClient({String? baseUrl, http.Client? httpClient})
      : baseUrl = baseUrl ?? defaultBaseUrl,
        _http = httpClient ?? http.Client();

  static const String defaultBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );

  final String baseUrl;
  final http.Client _http;

  String? _token;

  /// The currently attached bearer token (null when signed out).
  String? get token => _token;

  void setToken(String? token) => _token = token;

  /// The server origin, i.e. the base URL without the trailing `/api`.
  ///
  /// Room-type images are returned by the API as root-relative paths such as
  /// `/assets/img/room-classic.jpg`; the mobile app must turn those into an
  /// absolute URL before it can load them.
  static String originOf(String baseUrl) {
    final trimmed =
        baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;
    const suffix = '/api';
    return trimmed.endsWith(suffix)
        ? trimmed.substring(0, trimmed.length - suffix.length)
        : trimmed;
  }

  /// Absolute origin for the app's configured base URL.
  String get origin => originOf(baseUrl);

  /// Resolve a possibly root-relative asset path against [baseUrl].
  static String resolveAsset(String? path, {String? baseUrl}) {
    if (path == null || path.trim().isEmpty) {
      return '';
    }

    final uri = Uri.tryParse(path);
    if (uri != null && uri.hasScheme) {
      return path; // already absolute
    }

    final origin = originOf(baseUrl ?? defaultBaseUrl);
    return path.startsWith('/') ? '$origin$path' : '$origin/$path';
  }

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
  }) =>
      _send('GET', path, query: query);

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
  }) =>
      _send('POST', path, body: body);

  Future<Map<String, dynamic>> put(
    String path, {
    Map<String, dynamic>? body,
  }) =>
      _send('PUT', path, body: body);

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, dynamic>? body,
  }) =>
      _send('DELETE', path, body: body);

  Uri _uri(String path, Map<String, dynamic>? query) {
    final normalised = path.startsWith('/') ? path : '/$path';
    final uri = Uri.parse('$baseUrl$normalised');
    if (query == null || query.isEmpty) {
      return uri;
    }
    return uri.replace(
      queryParameters: query.map(
        (key, value) => MapEntry(key, value.toString()),
      ),
    );
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? query,
    Map<String, dynamic>? body,
  }) async {
    final uri = _uri(path, query);
    final headers = <String, String>{'Accept': 'application/json'};

    if (_token != null) {
      headers['Authorization'] = 'Bearer $_token';
    }

    String? payload;
    if (body != null) {
      headers['Content-Type'] = 'application/json';
      payload = jsonEncode(body);
    }

    http.Response response;
    try {
      switch (method) {
        case 'GET':
          response = await _http.get(uri, headers: headers);
          break;
        case 'POST':
          response = await _http.post(uri, headers: headers, body: payload);
          break;
        case 'PUT':
          response = await _http.put(uri, headers: headers, body: payload);
          break;
        case 'DELETE':
          response = await _http.delete(uri, headers: headers, body: payload);
          break;
        default:
          throw ArgumentError('Unsupported HTTP method: $method');
      }
    } on ApiException {
      rethrow;
    } catch (_) {
      throw ApiException(
        'Cannot reach the hotel server. Please check your connection and try again.',
      );
    }

    return _decode(response);
  }

  Map<String, dynamic> _decode(http.Response response) {
    Map<String, dynamic> json = const <String, dynamic>{};

    if (response.body.isNotEmpty) {
      try {
        final decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic>) {
          json = decoded;
        }
      } on FormatException {
        json = const <String, dynamic>{};
      }
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return json;
    }

    throw ApiException(
      (json['message'] as String?) ??
          'Request failed (HTTP ${response.statusCode}).',
      statusCode: response.statusCode,
      reason: json['reason'] as String?,
    );
  }

  void close() => _http.close();
}
