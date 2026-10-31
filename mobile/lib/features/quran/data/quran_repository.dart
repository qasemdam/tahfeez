import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../domain/surah.dart';

/// Read-only reference data. The server serves it through QuranDataProvider;
/// the current binding is a development fixture (OD-029).
class QuranRepository {
  QuranRepository(this._api);

  final ApiClient _api;

  Future<List<Surah>> surahs() async {
    final response = await _api.dio.get<Map<String, dynamic>>('/quran/surahs');
    final List<dynamic> rows = response.data!['data'] as List<dynamic>;

    return rows
        .map((dynamic row) => Surah.fromJson(row as Map<String, dynamic>))
        .toList(growable: false);
  }
}

final Provider<QuranRepository> quranRepositoryProvider =
    Provider<QuranRepository>((Ref ref) {
      return QuranRepository(ref.watch(apiClientProvider));
    });

final FutureProvider<List<Surah>> surahsProvider = FutureProvider<List<Surah>>((
  Ref ref,
) {
  return ref.watch(quranRepositoryProvider).surahs();
});
