import '../api/api_client.dart';

/// Resolve a root-relative asset path returned by the API (for example
/// `/assets/img/room-classic.jpg`) into an absolute URL the app can load.
String assetUrl(String? path) => ApiClient.resolveAsset(path);
