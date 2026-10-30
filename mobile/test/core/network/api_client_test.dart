import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/network/api_client.dart';

DioException _error({
  int? status,
  DioExceptionType type = DioExceptionType.badResponse,
}) {
  final RequestOptions options = RequestOptions(path: '/x');

  return DioException(
    requestOptions: options,
    response: status == null
        ? null
        : Response<dynamic>(requestOptions: options, statusCode: status),
    type: type,
  );
}

void main() {
  test('401 maps to an invalid-credentials message', () {
    expect(describeApiError(_error(status: 401)), 'بيانات الدخول غير صحيحة');
  });

  test('422 maps to a validation hint', () {
    expect(describeApiError(_error(status: 422)), 'تحقق من البيانات المدخلة');
  });

  test('5xx maps to a server-error message', () {
    expect(
      describeApiError(_error(status: 500)),
      'حدث خطأ في الخادم، حاول لاحقًا',
    );
  });

  test('connection errors map to a network message', () {
    expect(
      describeApiError(_error(type: DioExceptionType.connectionError)),
      'تعذر الاتصال بالخادم',
    );
  });

  test('unknown errors fall back to a generic message', () {
    expect(describeApiError(Exception('boom')), 'حدث خطأ غير متوقع');
  });
}
