import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/quran/data/quran_repository.dart';
import 'package:tahfeez/features/quran/domain/surah.dart';

import '../../../helpers/fake_http_adapter.dart';

void main() {
  late ApiClient api;

  setUp(() => api = ApiClient());
  tearDown(() => api.close());

  test('lists surahs', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/quran/surahs');

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <dynamic>[
          <String, dynamic>{
            'id': 1,
            'name_ar': 'الفاتحة',
            'ayah_count': 7,
            'page_start': 1,
            'page_end': 1,
          },
        ],
      });
    });

    final List<Surah> surahs = await QuranRepository(api).surahs();

    expect(surahs, hasLength(1));
    expect(surahs.single.nameAr, 'الفاتحة');
    expect(surahs.single.ayahCount, 7);
  });
}
