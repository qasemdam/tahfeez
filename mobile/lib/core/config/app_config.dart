/// Compile-time configuration.
///
/// The API base URL is injected at build time:
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api/v1
class AppConfig {
  const AppConfig._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  static const Duration requestTimeout = Duration(seconds: 20);
}
