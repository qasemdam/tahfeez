import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/quran/domain/surah.dart';

void main() {
  test('parses the surah payload', () {
    final Surah surah = Surah.fromJson(<String, dynamic>{
      'id': 2,
      'name_ar': 'البقرة',
      'ayah_count': 286,
      'page_start': 2,
      'page_end': 49,
    });

    expect(surah.id, 2);
    expect(surah.nameAr, 'البقرة');
    expect(surah.ayahCount, 286);
    expect(surah.pageStart, 2);
    expect(surah.pageEnd, 49);
  });

  test('falls back safely when fields are missing', () {
    final Surah surah = Surah.fromJson(<String, dynamic>{'id': 1});

    expect(surah.nameAr, '');
    expect(surah.ayahCount, 0);
    expect(surah.pageStart, 0);
  });
}
