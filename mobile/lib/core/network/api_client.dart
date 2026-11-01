import 'package:dio/dio.dart';

import '../config/app_config.dart';

/// Thin wrapper around Dio.
///
/// The token is held in memory only; persistence lives in [SessionStore].
class ApiClient {
  ApiClient() : _dio = Dio() {
    _dio.options = BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: AppConfig.requestTimeout,
      receiveTimeout: AppConfig.requestTimeout,
      headers: <String, dynamic>{'Accept': 'application/json'},
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (RequestOptions options, RequestInterceptorHandler handler) {
          final String? token = this.token;
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (DioException error, ErrorInterceptorHandler handler) {
          handler.next(error);
        },
      ),
    );
  }

  final Dio _dio;

  String? token;

  Dio get dio => _dio;

  void close() => _dio.close(force: true);
}

/// Turns a Dio failure into a short Arabic message for the UI.
String describeApiError(Object error) {
  if (error is DioException) {
    final int? status = error.response?.statusCode;
    if (status == 401) {
      return 'بيانات الدخول غير صحيحة';
    }
    if (status == 422) {
      return 'تحقق من البيانات المدخلة';
    }
    if (status != null && status >= 500) {
      return 'حدث خطأ في الخادم، حاول لاحقًا';
    }
    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.connectionError) {
      return 'تعذر الاتصال بالخادم';
    }
  }
  return 'حدث خطأ غير متوقع';
}
