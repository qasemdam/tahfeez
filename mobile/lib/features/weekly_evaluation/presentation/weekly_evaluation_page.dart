import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/weekly_evaluation_repository.dart';
import '../domain/weekly_evaluation.dart';

/// Shows the stored weekly evaluations of one student and computes a new one.
///
/// The client never decides where a week starts or ends: it sends any date
/// inside the week and displays the boundaries the server froze.
class WeeklyEvaluationPage extends ConsumerStatefulWidget {
  const WeeklyEvaluationPage({
    super.key,
    required this.studentId,
    required this.studentName,
  });

  final int studentId;
  final String studentName;

  @override
  ConsumerState<WeeklyEvaluationPage> createState() =>
      _WeeklyEvaluationPageState();
}

class _WeeklyEvaluationPageState extends ConsumerState<WeeklyEvaluationPage> {
  late String _weekDate;

  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _weekDate = _formatDate(DateTime.now());
  }

  static String _formatDate(DateTime date) {
    final String month = date.month.toString().padLeft(2, '0');
    final String day = date.day.toString().padLeft(2, '0');

    return '${date.year}-$month-$day';
  }

  Future<void> _pickWeekDate() async {
    final DateTime now = DateTime.now();
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: DateTime.tryParse(_weekDate) ?? now,
      firstDate: DateTime(now.year - 5),
      lastDate: now,
    );

    if (picked != null) {
      setState(() => _weekDate = _formatDate(picked));
    }
  }

  Future<void> _compute() async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref
          .read(weeklyEvaluationRepositoryProvider)
          .create(
            WeeklyEvaluationSubmission(
              studentId: widget.studentId,
              weekDate: _weekDate,
            ),
          );

      ref.invalidate(studentWeeklyEvaluationsProvider(widget.studentId));

      if (mounted) {
        // A duplicate week returns the stored row, so the wording stays
        // neutral rather than claiming a new computation.
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('تم حفظ تقييم الأسبوع')));
      }
    } catch (error) {
      if (mounted) {
        setState(() => _error = _describe(error));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _approve(WeeklyEvaluation evaluation) async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref.read(weeklyEvaluationRepositoryProvider).approve(evaluation.id);
      ref.invalidate(studentWeeklyEvaluationsProvider(widget.studentId));

      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('تم اعتماد التقييم')));
      }
    } catch (error) {
      if (mounted) {
        setState(() => _error = _describe(error));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  /// Prefers the server's first validation message, then the shared mapper.
  static String _describe(Object error) {
    if (error is DioException) {
      final dynamic body = error.response?.data;
      if (body is Map<String, dynamic>) {
        final dynamic errors = body['errors'];
        if (errors is Map<String, dynamic>) {
          for (final dynamic value in errors.values) {
            if (value is List<dynamic> && value.isNotEmpty) {
              return value.first.toString();
            }
          }
        }
      }
    }

    return describeApiError(error);
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AsyncValue<List<WeeklyEvaluation>> evaluations = ref.watch(
      studentWeeklyEvaluationsProvider(widget.studentId),
    );
    final bool canApprove =
        ref
            .watch(authControllerProvider)
            .valueOrNull
            ?.user
            .can('evaluation.approve') ??
        false;

    return Scaffold(
      appBar: AppBar(title: const Text('التقييم الأسبوعي')),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(studentWeeklyEvaluationsProvider(widget.studentId));
          await ref.read(
            studentWeeklyEvaluationsProvider(widget.studentId).future,
          );
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          children: <Widget>[
            _buildHeader(theme),
            const SizedBox(height: 16),
            if (_error != null) ...<Widget>[
              Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
              const SizedBox(height: 12),
            ],
            evaluations.when(
              loading: () => const Padding(
                padding: EdgeInsets.symmetric(vertical: 24),
                child: LinearProgressIndicator(),
              ),
              error: (Object error, StackTrace _) => Text(
                describeApiError(error),
                style: TextStyle(color: theme.colorScheme.error),
              ),
              data: (List<WeeklyEvaluation> rows) {
                if (rows.isEmpty) {
                  return const Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: Text('لا يوجد تقييم محفوظ لهذا الطالب بعد.'),
                  );
                }

                return Column(
                  children: rows
                      .map(
                        (WeeklyEvaluation row) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _EvaluationCard(
                            evaluation: row,
                            canApprove: canApprove && !row.isApproved,
                            busy: _busy,
                            onApprove: () => _approve(row),
                          ),
                        ),
                      )
                      .toList(growable: false),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader(ThemeData theme) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
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
                        'اختر أي يوم داخل الأسبوع: $_weekDate',
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'تغيير التاريخ',
                  onPressed: _busy ? null : _pickWeekDate,
                  icon: const Icon(Icons.calendar_month_rounded),
                ),
              ],
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: _busy ? null : _compute,
              icon: _busy
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.calculate_rounded),
              label: const Text('احتساب تقييم الأسبوع'),
            ),
            const SizedBox(height: 8),
            Text(
              'يُحسب الأسبوع من إعداد المركز، ويُحفظ التقييم كسجل تاريخي ثابت.',
              style: theme.textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _EvaluationCard extends StatelessWidget {
  const _EvaluationCard({
    required this.evaluation,
    required this.canApprove,
    required this.busy,
    required this.onApprove,
  });

  final WeeklyEvaluation evaluation;
  final bool canApprove;
  final bool busy;
  final VoidCallback onApprove;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    'من ${evaluation.weekStartDate} إلى ${evaluation.weekEndDate}',
                    style: theme.textTheme.titleSmall,
                  ),
                ),
                Chip(
                  label: Text(evaluation.statusLabel),
                  visualDensity: VisualDensity.compact,
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: <Widget>[
                _Metric(
                  label: 'المطلوب',
                  value: _formatNumber(evaluation.requiredPages),
                ),
                _Metric(
                  label: 'المنجز',
                  value: _formatNumber(evaluation.actualPages),
                ),
                _Metric(
                  label: 'الإنجاز',
                  value: '${_formatNumber(evaluation.achievementPercentage)}%',
                  highlight: true,
                ),
                _Metric(
                  label: 'الدرجة',
                  value: evaluation.hasWeightedScore
                      ? _formatNumber(evaluation.weightedScore)
                      : 'غير محسوبة',
                ),
              ],
            ),
            if (evaluation.blockedCriteria.isNotEmpty) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                'معايير موقوفة: ${evaluation.blockedCriteria.join('، ')}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.error,
                ),
              ),
            ],
            if (evaluation.includedComponents.isNotEmpty) ...<Widget>[
              const SizedBox(height: 4),
              ExpansionTile(
                tilePadding: EdgeInsets.zero,
                childrenPadding: EdgeInsets.zero,
                title: Text(
                  'تفاصيل المعايير',
                  style: theme.textTheme.bodySmall,
                ),
                children: evaluation.includedComponents
                    .map(
                      (EvaluationComponent component) => Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Row(
                          children: <Widget>[
                            Expanded(child: Text(component.label)),
                            Text(
                              _formatNumber(component.normalized),
                              style: theme.textTheme.bodySmall,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              'وزن ${_formatNumber(component.weight)}',
                              style: theme.textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                    )
                    .toList(growable: false),
              ),
            ],
            if (canApprove) ...<Widget>[
              const SizedBox(height: 8),
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: OutlinedButton.icon(
                  onPressed: busy ? null : onApprove,
                  icon: const Icon(Icons.verified_rounded),
                  label: const Text('اعتماد'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.label,
    required this.value,
    this.highlight = false,
  });

  final String label;
  final String value;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(label, style: theme.textTheme.bodySmall),
          const SizedBox(height: 2),
          Text(
            value,
            style: highlight
                ? theme.textTheme.titleMedium?.copyWith(
                    color: theme.colorScheme.primary,
                    fontWeight: FontWeight.bold,
                  )
                : theme.textTheme.titleSmall,
          ),
        ],
      ),
    );
  }
}

/// Trims trailing zeros so 1.50 reads as 1.5 and 200.00 as 200.
String _formatNumber(double? value) {
  if (value == null) {
    return '—';
  }

  final String text = value.toStringAsFixed(2);

  return text.replaceFirst(RegExp(r'\.?0+$'), '');
}
