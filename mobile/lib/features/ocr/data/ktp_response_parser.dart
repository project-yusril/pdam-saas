class KtpResponseParser {
  const KtpResponseParser._();

  static Map<String, String> parse(Object? response) {
    if (response is! Map) {
      throw const FormatException('KTP OCR response must be an object.');
    }
    final data = response['data'];
    if (data is! Map) {
      throw const FormatException('KTP OCR response is missing data.');
    }
    final parsed = data['parsed'];
    if (parsed is! Map || parsed.isEmpty) {
      throw const FormatException('KTP OCR response is missing parsed data.');
    }

    final result = <String, String>{};
    for (final entry in parsed.entries) {
      if (entry.key is! String) {
        throw const FormatException('KTP OCR field names must be strings.');
      }
      result[entry.key as String] = entry.value?.toString() ?? '';
    }
    result['ktp_photo_url'] = data['ktp_photo_url']?.toString() ?? '';
    result['ktp_file_type'] = data['ktp_file_type']?.toString() ?? '';
    return result;
  }
}
