import 'package:flutter/material.dart';

class KtpReviewConfirmation extends StatefulWidget {
  const KtpReviewConfirmation({
    super.key,
    required this.initialData,
    required this.onConfirmed,
  });

  final Map<String, String> initialData;
  final ValueChanged<Map<String, String>> onConfirmed;

  @override
  State<KtpReviewConfirmation> createState() => _KtpReviewConfirmationState();
}

class _KtpReviewConfirmationState extends State<KtpReviewConfirmation> {
  late Map<String, TextEditingController> _controllers;

  @override
  void initState() {
    super.initState();
    _createControllers();
  }

  @override
  void didUpdateWidget(KtpReviewConfirmation oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.initialData != widget.initialData) {
      _disposeControllers();
      _createControllers();
    }
  }

  void _createControllers() {
    _controllers = {
      for (final entry in widget.initialData.entries)
        entry.key: TextEditingController(text: entry.value),
    };
  }

  void _disposeControllers() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
  }

  @override
  void dispose() {
    _disposeControllers();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final editable = _controllers.entries
        .where((entry) => !entry.key.startsWith('ktp_'))
        .toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Periksa dan koreksi data KTP sebelum konfirmasi.',
          style: TextStyle(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 8),
        for (final entry in editable) ...[
          TextField(
            key: ValueKey('ktp-field-${entry.key}'),
            controller: entry.value,
            decoration: InputDecoration(
              labelText: entry.key.replaceAll('_', ' '),
            ),
          ),
          const SizedBox(height: 8),
        ],
        FilledButton(
          key: const ValueKey('confirm-ktp-review'),
          onPressed: () => widget.onConfirmed({
            for (final entry in _controllers.entries)
              entry.key: entry.value.text.trim(),
          }),
          child: const Text('Konfirmasi data KTP'),
        ),
      ],
    );
  }
}
