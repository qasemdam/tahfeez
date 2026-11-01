import '../../../core/domain/assessment.dart';

class Recitation {
  const Recitation({
    required this.id,
    required this.studentId,
    required this.studentName,
    required this.groupId,
    required this.groupName,
    required this.teacherName,
    required this.recitationDate,
    required this.surahId,
    required this.surahName,
    required this.fromAyah,
    required this.toAyah,
    this.fromPage,
    this.toPage,
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
  final String recitationDate;
  final int surahId;
  final String surahName;
  final int fromAyah;
  final int toAyah;
  final int? fromPage;
  final int? toPage;
  final RecitationGrade? grade;
  final int? hifzErrors;
  final int? tajweedErrors;
  final int? readingErrors;
  final String? notes;

  factory Recitation.fromJson(Map<String, dynamic> json) {
    return Recitation(
      id: json['id'] as int,
      studentId: json['student_id'] as int? ?? 0,
      studentName: json['student_name'] as String? ?? '',
      groupId: json['group_id'] as int? ?? 0,
      groupName: json['group_name'] as String? ?? '',
      teacherName: json['teacher_name'] as String? ?? '',
      recitationDate: json['recitation_date'] as String? ?? '',
      surahId: json['surah_id'] as int? ?? 0,
      surahName: json['surah_name'] as String? ?? '',
      fromAyah: json['from_ayah'] as int? ?? 0,
      toAyah: json['to_ayah'] as int? ?? 0,
      fromPage: json['from_page'] as int?,
      toPage: json['to_page'] as int?,
      grade: RecitationGrade.fromWire(json['grade'] as String?),
      hifzErrors: json['hifz_errors'] as int?,
      tajweedErrors: json['tajweed_errors'] as int?,
      readingErrors: json['reading_errors'] as int?,
      notes: json['notes'] as String?,
    );
  }
}

/// The payload for a new recitation. No amount is sent: recitation records
/// the range and quality, not a page amount.
class RecitationSubmission {
  const RecitationSubmission({
    required this.studentId,
    required this.recitationDate,
    required this.surahId,
    required this.fromAyah,
    required this.toAyah,
    this.fromPage,
    this.toPage,
    this.grade,
    this.hifzErrors,
    this.tajweedErrors,
    this.readingErrors,
    this.notes,
  });

  final int studentId;
  final String recitationDate;
  final int surahId;
  final int fromAyah;
  final int toAyah;
  final int? fromPage;
  final int? toPage;
  final RecitationGrade? grade;
  final int? hifzErrors;
  final int? tajweedErrors;
  final int? readingErrors;
  final String? notes;

  Map<String, dynamic> toJson() => <String, dynamic>{
    'student_id': studentId,
    'recitation_date': recitationDate,
    'surah_id': surahId,
    'from_ayah': fromAyah,
    'to_ayah': toAyah,
    if (fromPage != null) 'from_page': fromPage,
    if (toPage != null) 'to_page': toPage,
    if (grade != null) 'grade': grade!.wire,
    if (hifzErrors != null) 'hifz_errors': hifzErrors,
    if (tajweedErrors != null) 'tajweed_errors': tajweedErrors,
    if (readingErrors != null) 'reading_errors': readingErrors,
    if (notes != null && notes!.isNotEmpty) 'notes': notes,
  };
}
