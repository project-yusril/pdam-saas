import 'package:flutter_test/flutter_test.dart';
import 'package:pdam_mobile/features/ocr/data/ktp_response_parser.dart';

void main() {
  test('normalizes parsed values and preserves upload metadata', () {
    final result = KtpResponseParser.parse({
      'data': {
        'parsed': {'nik': 123, 'full_name': 'Ayu', 'birth_date': null},
        'ktp_photo_url': 'tenant/ktp/a.jpg',
        'ktp_file_type': 'jpg',
      },
    });
    expect(result['nik'], '123');
    expect(result['full_name'], 'Ayu');
    expect(result['birth_date'], '');
    expect(result['ktp_photo_url'], 'tenant/ktp/a.jpg');
    expect(result['ktp_file_type'], 'jpg');
  });

  test('allows absent optional upload metadata', () {
    final result = KtpResponseParser.parse({
      'data': {
        'parsed': {'nik': '123'}
      },
    });
    expect(result['ktp_photo_url'], '');
    expect(result['ktp_file_type'], '');
  });

  test('rejects non-object response', () {
    expect(() => KtpResponseParser.parse([]), throwsFormatException);
  });

  test('rejects missing data envelope', () {
    expect(() => KtpResponseParser.parse({}), throwsFormatException);
  });

  test('rejects empty parsed data', () {
    expect(
      () => KtpResponseParser.parse({
        'data': {'parsed': {}}
      }),
      throwsFormatException,
    );
  });
}
