class Surah {
  const Surah({
    required this.id,
    required this.nameAr,
    required this.ayahCount,
    required this.pageStart,
    required this.pageEnd,
  });

  final int id;
  final String nameAr;
  final int ayahCount;
  final int pageStart;
  final int pageEnd;

  factory Surah.fromJson(Map<String, dynamic> json) {
    return Surah(
      id: json['id'] as int,
      nameAr: json['name_ar'] as String? ?? '',
      ayahCount: json['ayah_count'] as int? ?? 0,
      pageStart: json['page_start'] as int? ?? 0,
      pageEnd: json['page_end'] as int? ?? 0,
    );
  }
}
