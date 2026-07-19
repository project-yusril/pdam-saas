class BaseUrlPolicy {
  const BaseUrlPolicy._();

  static String select({
    required bool production,
    required String productionUrl,
    required String debugUrl,
  }) {
    final selected = production ? productionUrl : debugUrl;
    final uri = Uri.tryParse(selected);
    if (uri == null || !uri.hasAuthority || uri.host.isEmpty) {
      throw ArgumentError.value(selected, 'baseUrl', 'must be an absolute URL');
    }
    if (production && uri.scheme != 'https') {
      throw StateError('Production API_BASE_URL must use HTTPS.');
    }
    if (uri.userInfo.isNotEmpty ||
        uri.query.isNotEmpty ||
        uri.fragment.isNotEmpty) {
      throw ArgumentError.value(
        selected,
        'baseUrl',
        'must not contain credentials, a query, or a fragment',
      );
    }
    return uri.toString().replaceFirst(RegExp(r'/$'), '');
  }
}
