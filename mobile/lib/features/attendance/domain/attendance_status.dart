/// Attendance statuses. The internal value is the server's wire value; the
/// label is what the Arabic UI shows.
enum AttendanceStatus {
  present('present', 'حاضر'),
  excused('excused_absence', 'معذور'),
  unexcused('unexcused_absence', 'غائب'),
  late('late', 'متأخر');

  const AttendanceStatus(this.wire, this.label);

  final String wire;
  final String label;

  bool get needsLateMinutes => this == AttendanceStatus.late;

  static AttendanceStatus? fromWire(String? value) {
    for (final AttendanceStatus status in AttendanceStatus.values) {
      if (status.wire == value) {
        return status;
      }
    }

    return null;
  }
}
