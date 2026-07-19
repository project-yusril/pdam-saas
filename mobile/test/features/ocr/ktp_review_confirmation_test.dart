import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pdam_mobile/features/ocr/presentation/widgets/ktp_review_confirmation.dart';

void main() {
  testWidgets('callback does not fire before explicit confirmation',
      (tester) async {
    var calls = 0;
    await tester.pumpWidget(_app(KtpReviewConfirmation(
      initialData: const {'nik': '123', 'full_name': 'Ayu'},
      onConfirmed: (_) => calls++,
    )));
    expect(calls, 0);
    await tester.enterText(find.byKey(const ValueKey('ktp-field-nik')), '456');
    expect(calls, 0);
  });

  testWidgets('confirmation returns edited and trimmed values', (tester) async {
    Map<String, String>? confirmed;
    await tester.pumpWidget(_app(KtpReviewConfirmation(
      initialData: const {
        'nik': '123',
        'full_name': 'Ayu',
        'ktp_photo_url': 'tenant/a.jpg',
      },
      onConfirmed: (data) => confirmed = data,
    )));
    await tester.enterText(
      find.byKey(const ValueKey('ktp-field-full_name')),
      '  Ayu Baru  ',
    );
    await tester.tap(find.byKey(const ValueKey('confirm-ktp-review')));
    expect(confirmed!['full_name'], 'Ayu Baru');
    expect(confirmed!['ktp_photo_url'], 'tenant/a.jpg');
  });

  testWidgets('upload metadata is not rendered as editable input',
      (tester) async {
    await tester.pumpWidget(_app(KtpReviewConfirmation(
      initialData: const {'nik': '123', 'ktp_file_type': 'jpg'},
      onConfirmed: (_) {},
    )));
    expect(find.byKey(const ValueKey('ktp-field-nik')), findsOneWidget);
    expect(find.byKey(const ValueKey('ktp-field-ktp_file_type')), findsNothing);
  });
}

Widget _app(Widget child) => MaterialApp(home: Scaffold(body: child));
