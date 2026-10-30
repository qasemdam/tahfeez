import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

typedef AdapterHandler = Future<ResponseBody> Function(RequestOptions options);

/// A dependency-free Dio adapter for tests.
///
/// Returns canned responses instead of touching the network, so repository
/// tests stay fast and deterministic.
class FakeHttpAdapter implements HttpClientAdapter {
  FakeHttpAdapter(this.handler);

  final AdapterHandler handler;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) {
    return handler(options);
  }

  @override
  void close({bool force = false}) {}

  /// Convenience: encode [body] as a JSON response.
  static ResponseBody json(Object body, {int statusCode = 200}) {
    return ResponseBody.fromString(
      jsonEncode(body),
      statusCode,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      },
    );
  }
}
