import '../../../core/domain/assessment.dart';

export '../../../core/domain/assessment.dart';

class Memorization {
  const Memorization({
    required this.id,
    required this.studentId,
    required this.studentName,
    required this.groupId,
    required this.groupName,
    required this.teacherName,
    required this.memorizationDate,
    required this.amountPages,
    required this.amountSource,
    this.surahId,
    this.surahName,
    this.fromAyah,
    this.toAyah,
    this.fromPage,
    this.toPage,
    this.amountLines,
    this.grade,
    this.hifzErrors,
    this.tajweedErrors,
    this.readingErrors,
    this.notes,
  });

  final int id;
  final int studentId;
  final String studentName;
  final int groupId;
  final String groupName;
  final String teacherName;
  final String memorizationDate;
  final String amountPages;
  final AmountSource amountSource;
  final int? surahId;
  final String? surahName;
  final int? fromAyah;
  final int? toAyah;
  final int? fromPage;
  final int? toPage;
  final int? amountLines;
  final RecitationGrade? grade;
  final int? hifzErrors;
  final int? tajweedErrors;
  final int? readingErrors;
  final String? notes;

  factory Memorization.fromJson(Map<String, dynamic> json) {
    return Memorization(
      id: json['id'] as int,
      studentId: json['student_id'] as int? ?? 0,
      studentName: json['student_name'] as String? ?? '',
      groupId: json['group_id'] as int? ?? 0,
      groupName: json['group_name'] as String? ?? '',
      teacherName: json['teacher_name'] as String? ?? '',
      memorizationDate: json['memorization_date'] as String? ?? '',
      amountPages: json['amount_pages'] as String? ?? '0',
      amountSource: AmountSource.fromWire(json['amount_source'] as String?),
      surahId: json['surah_id'] as int?,
      surahName: json['surah_name'] as String?,
      fromAyah: json['from_ayah'] as int?,
      toAyah: json['to_ayah'] as int?,
      fromPage: json['from_page'] as int?,
      toPage: json['to_page'] as int?,
      amountLines: json['amount_lines'] as int?,
      grade: RecitationGrade.fromWire(json['grade'] as String?),
      hifzErrors: json['hifz_errors'] as int?,
      tajweedErrors: json['tajweed_errors'] as int?,
      readingErrors: json['reading_errors'] as int?,
      notes: json['notes'] as String?,
    );
  }
}

/// The payload for a new record. Only the fields relevant to the chosen mode
/// are sent; the server validates the rest.
class MemorizationSubmission {
  const MemorizationSubmission({
    required this.studentId,
    required this.memorizationDate,
    required this.amountSource,
    this.amountPages,
    this.surahId,
    this.fromAyah,
    this.toAyah,
    this.fromPage,
    this.toPage,
    this.grade,
    this.hifzErrors,
    this.tajweedErrors,
    this.readingErrors,
    this.notes,
  });

  final int studentId;
  final String memorizationDate;
  final AmountSource amountSource;
  final String? amountPages;
  final int? surahId;
  final int? fromAyah;
  final int? toAyah;
  final int? fromPage;
  final int? toPage;
  final RecitationGrade? grade;
  final int? hifzErrors;
  final int? tajweedErrors;
  final int? readingErrors;
  final String? notes;

  Map<String, dynamic> toJson() => <String, dynamic>{
    'student_id': studentId,
    'memorization_date': memorizationDate,
    'amount_source': amountSource.wire,
    if (amountPages != null && amountPages!.isNotEmpty)
      'amount_pages': amountPages,
    if (surahId != null) 'surah_id': surahId,
    if (fromAyah != null) 'from_ayah': fromAyah,
    if (toAyah != null) 'to_ayah': toAyah,
    if (fromPage != null) 'from_page': fromPage,
    if (toPage != null) 'to_page': toPage,
    if (grade != null) 'grade': grade!.wire,
    if (hifzErrors != null) 'hifz_errors': hifzErrors,
    if (tajweedErrors != null) 'tajweed_errors': tajweedErrors,
    if (readingErrors != null) 'reading_errors': readingErrors,
    if (notes != null && notes!.isNotEmpty) 'notes': notes,
  };
}
