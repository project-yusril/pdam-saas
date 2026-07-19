import 'package:intl/intl.dart';

class FormatHelper {
  FormatHelper._();

  static final NumberFormat _currencyFormat = NumberFormat.currency(
    locale: 'id_ID',
    symbol: 'Rp',
    decimalDigits: 0,
  );

  static final NumberFormat _numberFormat =
      NumberFormat.decimalPattern('id_ID');

  static final DateFormat _dateFormat = DateFormat('dd MMMM yyyy', 'id_ID');
  static final DateFormat _dateTimeFormat =
      DateFormat('dd MMMM yyyy HH:mm', 'id_ID');
  static final DateFormat _shortDateFormat = DateFormat('dd/MM/yyyy', 'id_ID');
  static final DateFormat _monthYearFormat = DateFormat('MMMM yyyy', 'id_ID');
  static final DateFormat _monthFormat = DateFormat('MMM', 'id_ID');

  static String formatRupiah(num amount) {
    return _currencyFormat.format(amount);
  }

  static String formatNumber(num number) {
    return _numberFormat.format(number);
  }

  static String formatDate(DateTime date) {
    return _dateFormat.format(date);
  }

  static String formatDateTime(DateTime dateTime) {
    return _dateTimeFormat.format(dateTime);
  }

  static String formatShortDate(DateTime date) {
    return _shortDateFormat.format(date);
  }

  static String formatMonthYear(DateTime date) {
    return _monthYearFormat.format(date);
  }

  static String formatMonth(DateTime date) {
    return _monthFormat.format(date);
  }

  static String formatVolume(num volume) {
    if (volume >= 1000) {
      return '${(volume / 1000).toStringAsFixed(1)} m\u00B3';
    }
    return '${volume.toStringAsFixed(0)} liter';
  }

  static String formatRelativeTime(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inSeconds < 60) {
      return 'Baru saja';
    } else if (difference.inMinutes < 60) {
      return '${difference.inMinutes} menit yang lalu';
    } else if (difference.inHours < 24) {
      return '${difference.inHours} jam yang lalu';
    } else if (difference.inDays < 7) {
      return '${difference.inDays} hari yang lalu';
    } else if (difference.inDays < 30) {
      return '${(difference.inDays / 7).floor()} minggu yang lalu';
    } else {
      return formatShortDate(dateTime);
    }
  }

  static String abbreviateNumber(num number) {
    if (number >= 1000000) {
      return '${(number / 1000000).toStringAsFixed(1)}M';
    } else if (number >= 1000) {
      return '${(number / 1000).toStringAsFixed(1)}K';
    }
    return number.toString();
  }

  static String formatPeriod(String period) {
    if (period.length == 6) {
      final year = period.substring(0, 4);
      final month = period.substring(4, 6);
      final date = DateTime(int.parse(year), int.parse(month));
      return formatMonthYear(date);
    }
    return period;
  }

  static String maskPartialText(String text, {int visibleChars = 4}) {
    if (text.length <= visibleChars) return text;
    final visible = text.substring(0, visibleChars);
    return '$visible${'*' * (text.length - visibleChars)}';
  }
}
