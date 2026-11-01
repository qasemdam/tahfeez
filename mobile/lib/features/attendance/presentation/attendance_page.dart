import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../data/attendance_repository.dart';
import '../domain/attendance.dart';
import '../domain/attendance_status.dart';

/// The daily attendance sheet: pick a date, tap exceptions, save once.
/// Nothing is persisted until "حفظ الحضور" is pressed.
class AttendancePage extends ConsumerStatefulWidget {
  const AttendancePage({super.key, required this.groupId, this.initialDate});

  final int groupId;
  final String? initialDate;

  @override
  ConsumerState<AttendancePage> createState() => _AttendancePageState();
}

class _Draft {
  _Draft({required this.status, this.lateMinutes, this.notes});

  AttendanceStatus status;
  int? lateMinutes;
  String? notes;
}

class _AttendancePageState extends ConsumerState<AttendancePage> {
  late String _date;
  final Map<int, _Draft> _draft = <int, _Draft>{};
  String? _loadedKey;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _date = widget.initialDate ?? _formatDate(DateTime.now());
  }

  AttendanceSheetArgs get _args => (groupId: widget.groupId, date: _date);

  static String _formatDate(DateTime date) {
    final String month = date.month.toString().padLeft(2, '0');
    final String day = date.day.toString().padLeft(2, '0');

    return '${date.year}-$month-$day';
  }

  /// Populates local drafts from the sheet. Called during build; it never
  /// calls setState and keeps edits the teacher already made.
  void _seedDraft(GroupAttendanceSheet sheet) {
    final String key = '${sheet.groupId}|${sheet.date}';

    if (_loadedKey != key) {
      _loadedKey = key;
      _draft.clear();
    }

    for (final AttendanceSheetRow row in sheet.students) {
      _draft.putIfAbsent(
        row.studentId,
        () => _Draft(
          status: row.status ?? AttendanceStatus.present,
          lateMinutes: row.lateMinutes,
          notes: row.notes,
        ),
      );
    }
  }

  Future<void> _pickDate() async {
    final DateTime now = DateTime.now();
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: DateTime.tryParse(_date) ?? now,
      firstDate: DateTime(now.year - 5),
      lastDate: now,
    );

    if (picked == null) {
      return;
    }

    setState(() {
      _date = _formatDate(picked);
      _loadedKey = null;
      _draft.clear();
    });
  }

  Future<void> _save(GroupAttendanceSheet sheet) async {
    setState(() => _saving = true);

    try {
      final List<AttendanceSubmission> records = sheet.students
          .map((AttendanceSheetRow row) {
            final _Draft draft = _draft[row.studentId]!;

            return AttendanceSubmission(
              studentId: row.studentId,
              status: draft.status,
              lateMinutes: draft.status == AttendanceStatus.late
                  ? draft.lateMinutes
                  : null,
              notes: draft.notes,
            );
          })
          .toList(growable: false);

      await ref
          .read(attendanceRepositoryProvider)
          .saveSheet(widget.groupId, _date, records);

      ref.invalidate(groupAttendanceSheetProvider(_args));

      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('تم حفظ الحضور')));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(describeApiError(error))));
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<GroupAttendanceSheet> sheet = ref.watch(
      groupAttendanceSheetProvider(_args),
    );

    return Scaffold(
      appBar: AppBar(
        title: const Text('حضور الحلقة'),
        actions: <Widget>[
          IconButton(
            tooltip: 'اختيار التاريخ',
            onPressed: _pickDate,
            icon: const Icon(Icons.calendar_month_rounded),
          ),
        ],
      ),
      body: sheet.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object error, StackTrace _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Text(describeApiError(error)),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () =>
                    ref.invalidate(groupAttendanceSheetProvider(_args)),
                child: const Text('إعادة المحاولة'),
              ),
            ],
          ),
        ),
        data: (GroupAttendanceSheet value) {
          _seedDraft(value);

          return Column(
            children: <Widget>[
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        value.groupName,
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                    ),
                    TextButton.icon(
                      onPressed: _pickDate,
                      icon: const Icon(Icons.calendar_month_rounded, size: 18),
                      label: Text(_date),
                    ),
                  ],
                ),
              ),
              if (value.students.isEmpty)
                const Expanded(
                  child: Center(child: Text('لا يوجد طلاب في هذه الحلقة')),
                )
              else
                Expanded(
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    itemCount: value.students.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (BuildContext context, int index) {
                      final AttendanceSheetRow row = value.students[index];

                      return _AttendanceRow(
                        row: row,
                        draft: _draft[row.studentId]!,
                        onStatusChanged: (AttendanceStatus status) {
                          setState(() {
                            final _Draft draft = _draft[row.studentId]!;
                            draft.status = status;
                            if (status != AttendanceStatus.late) {
                              draft.lateMinutes = null;
                            }
                          });
                        },
                        onLateMinutesChanged: (int? minutes) {
                          setState(
                            () => _draft[row.studentId]!.lateMinutes = minutes,
                          );
                        },
                        onNotesChanged: (String? notes) {
                          setState(() => _draft[row.studentId]!.notes = notes);
                        },
                      );
                    },
                  ),
                ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  child: FilledButton.icon(
                    onPressed: _saving || value.students.isEmpty
                        ? null
                        : () => _save(value),
                    icon: _saving
                        ? const SizedBox(
                            height: 18,
                            width: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.save_rounded),
                    label: const Text('حفظ الحضور'),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _AttendanceRow extends StatelessWidget {
  const _AttendanceRow({
    required this.row,
    required this.draft,
    required this.onStatusChanged,
    required this.onLateMinutesChanged,
    required this.onNotesChanged,
  });

  final AttendanceSheetRow row;
  final _Draft draft;
  final ValueChanged<AttendanceStatus> onStatusChanged;
  final ValueChanged<int?> onLateMinutesChanged;
  final ValueChanged<String?> onNotesChanged;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                CircleAvatar(
                  child: Text(
                    row.fullName.isEmpty ? '؟' : row.fullName.substring(0, 1),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(row.fullName, style: theme.textTheme.titleMedium),
                      if (row.gradeLevel != null && row.gradeLevel!.isNotEmpty)
                        Text(row.gradeLevel!, style: theme.textTheme.bodySmall),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'ملاحظة',
                  onPressed: () => _editNotes(context),
                  icon: Icon(
                    draft.notes == null || draft.notes!.isEmpty
                        ? Icons.note_add_outlined
                        : Icons.sticky_note_2_rounded,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 4,
              children: AttendanceStatus.values
                  .map((AttendanceStatus status) {
                    return ChoiceChip(
                      label: Text(status.label),
                      selected: draft.status == status,
                      onSelected: (_) => onStatusChanged(status),
                    );
                  })
                  .toList(growable: false),
            ),
            if (draft.status == AttendanceStatus.late) ...<Widget>[
              const SizedBox(height: 8),
              TextFormField(
                initialValue: draft.lateMinutes?.toString() ?? '',
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'دقائق التأخير'),
                onChanged: (String value) =>
                    onLateMinutesChanged(int.tryParse(value)),
              ),
            ],
            if (draft.notes != null && draft.notes!.isNotEmpty) ...<Widget>[
              const SizedBox(height: 8),
              Text('ملاحظة: ${draft.notes}', style: theme.textTheme.bodySmall),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _editNotes(BuildContext context) async {
    final TextEditingController controller = TextEditingController(
      text: draft.notes ?? '',
    );

    final String? notes = await showDialog<String>(
      context: context,
      builder: (BuildContext dialogContext) => AlertDialog(
        title: const Text('ملاحظة'),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: const InputDecoration(hintText: 'ملاحظة اختيارية'),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.of(dialogContext).pop(controller.text.trim()),
            child: const Text('حفظ'),
          ),
        ],
      ),
    );

    controller.dispose();

    if (notes != null) {
      onNotesChanged(notes.isEmpty ? null : notes);
    }
  }
}
