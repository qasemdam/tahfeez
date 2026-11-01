import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/domain/assessment.dart';
import '../../../core/network/api_client.dart';
import '../../quran/data/quran_repository.dart';
import '../../quran/domain/surah.dart';
import '../data/review_repository.dart';
import '../domain/review.dart';

/// Records a review (Murajaʿah) for one student. Separate from new
/// memorization: it has its own table and never feeds memorization totals.
class ReviewPage extends ConsumerStatefulWidget {
  const ReviewPage({
    super.key,
    required this.studentId,
    required this.studentName,
  });

  final int studentId;
  final String studentName;

  @override
  ConsumerState<ReviewPage> createState() => _ReviewPageState();
}

class _ReviewPageState extends ConsumerState<ReviewPage> {
  late String _date;

  bool _saving = false;
  String? _error;

  AmountSource _source = AmountSource.calculated;
  int? _surahId;
  RecitationGrade? _grade;

  final TextEditingController _fromAyah = TextEditingController();
  final TextEditingController _toAyah = TextEditingController();
  final TextEditingController _fromPage = TextEditingController();
  final TextEditingController _toPage = TextEditingController();
  final TextEditingController _amount = TextEditingController();
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
    _fromAyah.dispose();
    _toAyah.dispose();
    _fromPage.dispose();
    _toPage.dispose();
    _amount.dispose();
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
    final int? from = _parse(_fromAyah);
    final int? to = _parse(_toAyah);

    if (_source == AmountSource.calculated) {
      if (_surahId == null) {
        return 'اختر السورة.';
      }

      if (from == null || to == null) {
        return 'أدخل نطاق الآيات.';
      }

      if (from > to) {
        return 'بداية النطاق بعد نهايته.';
      }

      return null;
    }

    final String amount = _amount.text.trim();
    if (amount.isEmpty) {
      return 'أدخل مقدار المراجعة.';
    }

    final double? value = double.tryParse(amount);
    if (value == null || value <= 0) {
      return 'المقدار يجب أن يكون رقمًا أكبر من صفر.';
    }

    if (_surahId != null || from != null || to != null) {
      if (_surahId == null || from == null || to == null) {
        return 'أكمل نطاق الآيات أو اتركه فارغًا.';
      }

      if (from > to) {
        return 'بداية النطاق بعد نهايته.';
      }
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

    final bool calculated = _source == AmountSource.calculated;

    final ReviewSubmission submission = ReviewSubmission(
      studentId: widget.studentId,
      reviewDate: _date,
      amountSource: _source,
      amountPages: calculated ? null : _amount.text.trim(),
      surahId: _surahId,
      fromAyah: _parse(_fromAyah),
      toAyah: _parse(_toAyah),
      fromPage: calculated ? null : _parse(_fromPage),
      toPage: calculated ? null : _parse(_toPage),
      grade: _grade,
      hifzErrors: _parse(_hifzErrors),
      tajweedErrors: _parse(_tajweedErrors),
      readingErrors: _parse(_readingErrors),
      notes: _notes.text.trim().isEmpty ? null : _notes.text.trim(),
    );

    try {
      await ref.read(reviewRepositoryProvider).create(submission);
      ref.invalidate(studentReviewsProvider(widget.studentId));

      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('تم حفظ المراجعة')));

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
      appBar: AppBar(title: const Text('تسجيل مراجعة')),
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
                          'تاريخ المراجعة: $_date',
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
          const SizedBox(height: 16),
          SegmentedButton<AmountSource>(
            segments: const <ButtonSegment<AmountSource>>[
              ButtonSegment<AmountSource>(
                value: AmountSource.calculated,
                label: Text('حساب تلقائي'),
              ),
              ButtonSegment<AmountSource>(
                value: AmountSource.manual,
                label: Text('مقدار يدوي'),
              ),
            ],
            selected: <AmountSource>{_source},
            onSelectionChanged: (Set<AmountSource> selected) {
              setState(() => _source = selected.first);
            },
          ),
          const SizedBox(height: 12),
          if (_source == AmountSource.manual) ...<Widget>[
            TextFormField(
              key: const Key('review.amount'),
              controller: _amount,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: const InputDecoration(labelText: 'المقدار (صفحات)'),
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
          ] else
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  'ستُحسب الصفحات والمقدار من نطاق الآيات وفق بيانات المصحف.',
                  style: theme.textTheme.bodySmall,
                ),
              ),
            ),
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
