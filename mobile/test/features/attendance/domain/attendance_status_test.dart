import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/attendance/domain/attendance_status.dart';

void main() {
  test('maps wire values to the Arabic labels', () {
    expect(AttendanceStatus.fromWire('present'), AttendanceStatus.present);
    expect(AttendanceStatus.present.label, 'حاضر');
    expect(
      AttendanceStatus.fromWire('excused_absence'),
      AttendanceStatus.excused,
    );
    expect(AttendanceStatus.excused.label, 'معذور');
    expect(
      AttendanceStatus.fromWire('unexcused_absence'),
      AttendanceStatus.unexcused,
    );
    expect(AttendanceStatus.unexcused.label, 'غائب');
    expect(AttendanceStatus.fromWire('late'), AttendanceStatus.late);
    expect(AttendanceStatus.late.label, 'متأخر');
  });

  test('unknown or null wire values map to null', () {
    expect(AttendanceStatus.fromWire(null), isNull);
    expect(AttendanceStatus.fromWire('unknown'), isNull);
  });

  test('only late needs minutes', () {
    expect(AttendanceStatus.late.needsLateMinutes, isTrue);
    expect(AttendanceStatus.present.needsLateMinutes, isFalse);
    expect(AttendanceStatus.excused.needsLateMinutes, isFalse);
    expect(AttendanceStatus.unexcused.needsLateMinutes, isFalse);
  });
}
