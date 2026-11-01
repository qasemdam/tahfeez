import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../quran/data/quran_repository.dart';
import '../../quran/domain/surah.dart';
import '../data/memorization_repository.dart';
import '../domain/memorization.dart';

/// Records new memorization for one student.
///
/// Simple mode is the default: date, amount, optional grade/notes, save.
/// Professional mode adds the Quran range, amount source and error counters.
class MemorizationPage extends ConsumerStatefulWidget {
  const MemorizationPage({
    super.key,
    required this.studentId,
    required this.studentName,
  });

  final int studentId;
  final String studentName;

  @override
  ConsumerState<MemorizationPage> createState() => _MemorizationPageState();
}

class _MemorizationPageState extends ConsumerState<MemorizationPage> {
  late String _date;

  bool _professional = false;
  bool _saving = false;
  String? _error;

  AmountSource _source = AmountSource.manual;
  int? _surahId;
  RecitationGrade? _grade;

  final TextEditingController _amount = TextEditingController();
  final TextEditingController _fromAyah = TextEditingController();
  final TextEditingController _toAyah = TextEditingController();
  final TextEditingController _fromPage = TextEditingController();
  final TextEditingController _toPage = TextEditingController();
  final TextEditingController _hifzErrors = TextEditingController();
  final TextEditingController _tajweedErrors = TextEditingController();
  final TextEditingController _readingErrors = TextEditingController();
  final TextEditingController _notes = TextEditingController();

  @override
  void initState() {
    super.initState();
    _date = _formatDate(DateTime.now());
  }

  @override
  void dispose() {
    _amount.dispose();
    _fromAyah.dispose();
    _toAyah.dispose();
    _fromPage.dispose();
    _toPage.dispose();
    _hifzErrors.dispose();
    _tajweedErrors.dispose();
    _readingErrors.dispose();
    _notes.dispose();
    super.dispose();
  }

  static String _formatDate(DateTime date) {
    final String month = date.month.toString().padLeft(2, '0');
    final String day = date.day.toString().padLeft(2, '0');

    return '${date.year}-$month-$day';
  }

  static int? _parse(TextEditingController controller) {
    return int.tryParse(controller.text.trim());
  }

  Future<void> _pickDate() async {
    final DateTime now = DateTime.now();
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: DateTime.tryParse(_date) ?? now,
      firstDate: DateTime(now.year - 5),
      lastDate: now,
    );

    if (picked != null) {
      setState(() => _date = _formatDate(picked));
    }
  }

  String? _validate() {
    if (_source == AmountSource.manual) {
      final String amount = _amount.text.trim();
      if (amount.isEmpty) {
        return 'أدخل مقدار الحفظ.';
      }

      final double? value = double.tryParse(amount);
      if (value == null || value <= 0) {
        return 'المقدار يجب أن يكون رقمًا أكبر من صفر.';
      }

      return null;
    }

    if (!_professional) {
      return 'الوضع التلقائي يحتاج السورة ونطاق الآيات.';
    }

    if (_surahId == null) {
      return 'اختر السورة.';
    }

    final int? from = _parse(_fromAyah);
    final int? to = _parse(_toAyah);

    if (from == null || to == null) {
      return 'أدخل نطاق الآيات.';
    }

    if (from > to) {
      return 'بداية النطاق بعد نهايته.';
    }

    return null;
  }

  Future<void> _save() async {
    final String? validation = _validate();

    if (validation != null) {
      setState(() => _error = validation);
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    final MemorizationSubmission submission = MemorizationSubmission(
      studentId: widget.studentId,
      memorizationDate: _date,
      amountSource: _source,
      amountPages: _source == AmountSource.manual ? _amount.text.trim() : null,
      surahId: _professional ? _surahId : null,
      fromAyah: _professional ? _parse(_fromAyah) : null,
      toAyah: _professional ? _parse(_toAyah) : null,
      fromPage: _professional ? _parse(_fromPage) : null,
      toPage: _professional ? _parse(_toPage) : null,
      grade: _grade,
      hifzErrors: _professional ? _parse(_hifzErrors) : null,
      tajweedErrors: _professional ? _parse(_tajweedErrors) : null,
      readingErrors: _professional ? _parse(_readingErrors) : null,
      notes: _notes.text.trim().isEmpty ? null : _notes.text.trim(),
    );

    try {
      await ref.read(memorizationRepositoryProvider).create(submission);
      ref.invalidate(studentMemorizationsProvider(widget.studentId));

      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('تم حفظ الحفظ')));

        if (Navigator.of(context).canPop()) {
          Navigator.of(context).pop(true);
        }
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = describeApiError(error);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('تسجيل حفظ')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: <Widget>[
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: <Widget>[
                  CircleAvatar(
                    child: Text(
                      widget.studentName.isEmpty
                          ? '؟'
                          : widget.studentName.substring(0, 1),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          widget.studentName,
                          style: theme.textTheme.titleMedium,
                        ),
                        Text(
                          'تاريخ الحفظ: $_date',
                          style: theme.textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'تغيير التاريخ',
                    onPressed: _pickDate,
                    icon: const Icon(Icons.calendar_month_rounded),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          SegmentedButton<bool>(
            segments: const <ButtonSegment<bool>>[
              ButtonSegment<bool>(value: false, label: Text('بسيط')),
              ButtonSegment<bool>(value: true, label: Text('احترافي')),
            ],
            selected: <bool>{_professional},
            onSelectionChanged: (Set<bool> selected) {
              setState(() {
                _professional = selected.first;
                _error = null;
              });
            },
          ),
          const SizedBox(height: 16),
          if (!_professional)
            TextFormField(
              controller: _amount,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: const InputDecoration(
                labelText: 'المقدار (صفحات)',
                helperText: 'مثال: 0.5 = نصف صفحة، 1 = صفحة كاملة',
              ),
            )
          else ...<Widget>[
            _buildSurahField(),
            const SizedBox(height: 12),
            Row(
              children: <Widget>[
                Expanded(
                  child: TextFormField(
                    controller: _fromAyah,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'من آية'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _toAyah,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'إلى آية'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: <Widget>[
                Expanded(
                  child: TextFormField(
                    controller: _fromPage,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'من صفحة (اختياري)',
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _toPage,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'إلى صفحة (اختياري)',
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            SegmentedButton<AmountSource>(
              segments: const <ButtonSegment<AmountSource>>[
                ButtonSegment<AmountSource>(
                  value: AmountSource.manual,
                  label: Text('مقدار يدوي'),
                ),
                ButtonSegment<AmountSource>(
                  value: AmountSource.calculated,
                  label: Text('حساب تلقائي'),
                ),
              ],
              selected: <AmountSource>{_source},
              onSelectionChanged: (Set<AmountSource> selected) {
                setState(() => _source = selected.first);
              },
            ),
            const SizedBox(height: 12),
            if (_source == AmountSource.manual)
              TextFormField(
                controller: _amount,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(labelText: 'المقدار (صفحات)'),
              )
            else
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(
                    'سيُحسب المقدار من نطاق الآيات وفق بيانات المصحف.',
                    style: theme.textTheme.bodySmall,
                  ),
                ),
              ),
            const SizedBox(height: 16),
            Text('أخطاء التسميع (اختياري)', style: theme.textTheme.titleSmall),
            const SizedBox(height: 8),
            Row(
              children: <Widget>[
                Expanded(
                  child: TextFormField(
                    controller: _hifzErrors,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'حفظ'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextFormField(
                    controller: _tajweedErrors,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'تجويد'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextFormField(
                    controller: _readingErrors,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'قراءة'),
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 16),
          Text('درجة التسميع (اختياري)', style: theme.textTheme.titleSmall),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: RecitationGrade.values
                .map((RecitationGrade grade) {
                  return ChoiceChip(
                    label: Text(grade.label),
                    selected: _grade == grade,
                    onSelected: (bool selected) {
                      setState(() => _grade = selected ? grade : null);
                    },
                  );
                })
                .toList(growable: false),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _notes,
            maxLines: 2,
            decoration: const InputDecoration(labelText: 'ملاحظات (اختياري)'),
          ),
          if (_error != null) ...<Widget>[
            const SizedBox(height: 12),
            Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
          ],
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: _saving ? null : _save,
            icon: _saving
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.save_rounded),
            label: const Text('حفظ'),
          ),
        ],
      ),
    );
  }

  Widget _buildSurahField() {
    final AsyncValue<List<Surah>> surahs = ref.watch(surahsProvider);

    return surahs.when(
      loading: () => const LinearProgressIndicator(),
      error: (Object error, StackTrace _) => Text(describeApiError(error)),
      data: (List<Surah> rows) {
        return DropdownMenu<int>(
          initialSelection: _surahId,
          label: const Text('السورة'),
          enableFilter: true,
          expandedInsets: EdgeInsets.zero,
          dropdownMenuEntries: rows
              .map(
                (Surah surah) => DropdownMenuEntry<int>(
                  value: surah.id,
                  label: surah.nameAr,
                ),
              )
              .toList(growable: false),
          onSelected: (int? value) {
            setState(() {
              _surahId = value;
              _fromAyah.clear();
              _toAyah.clear();
            });
          },
        );
      },
    );
  }
}
