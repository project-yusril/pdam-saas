/// API endpoints — SELARAS dengan backend `backend/routes/api.php`
/// Prefix global `/api/v1` sudah diset di ApiClient baseUrl.
/// Jangan tambahkan `/api/v1` di sini.
class Endpoints {
  Endpoints._();

  // ── Auth (tenant) ──────────────────────────────────────────────
  static const String login = '/login';
  static const String logout = '/logout';
  static const String refreshToken = '/refresh-token';
  static const String me = '/me';
  static const String updateProfile = '/profile/update';
  static const String changePassword = '/change-password';
  static const String registerFcmToken = '/fcm-token';

  // ── Portal Pelanggan ───────────────────────────────────────────
  static const String customerDashboard = '/portal/dashboard';
  static const String customerBills = '/portal/bills';
  static String customerBillDetail(int id) => '/portal/bills/${_id(id)}';
  static String customerBillPay(int id) => '/portal/bills/${_id(id)}/pay';
  static const String customerUsageHistory = '/portal/usage-history';
  static const String customerNotifications = '/portal/notifications';
  static String customerNotificationsRead(int id) =>
      '/portal/notifications/${_id(id)}/read';
  static const String customerNotificationsReadAll =
      '/portal/notifications/read-all';
  static const String customerComplaints = '/portal/complaints';
  static String customerComplaintDetail(int id) =>
      '/portal/complaints/${_id(id)}';
  static const String customerProfile = '/portal/profile';

  // ── Baca Meter (MTR) ───────────────────────────────────────────
  static const String meterRoutes = '/meter-routes';
  static String meterRouteTasks(int routeId) => '/meter-routes/${_id(routeId)}';
  static const String meterReadings = '/meter-readings';
  static const String meterReadingSubmit = '/meter-readings';
  static const String meterReadingHistory = '/meter-readings/route-progress';
  static const String meterReadingOcr = '/meter-readings/parse';

  // ── Survey & Pemasangan (SRV) ──────────────────────────────────
  static const String surveyTasks = '/prospects';
  static String surveyTaskDetail(int id) => '/prospects/${_id(id)}';
  static String surveySubmit(int id) => '/prospects/${_id(id)}/survey';
  static String surveyReview(int id) => '/survey-reports/${_id(id)}/review';

  // ── OCR ────────────────────────────────────────────────────────
  static const String ocrKtpUpload = '/prospects/upload-ktp';
  static const String ocrKtpParse = '/prospects/parse-ktp';

  // ── Offline Sync ───────────────────────────────────────────────
  static const String syncUpload = '/sync/upload';
  static const String syncDownload = '/sync/download';
  static const String syncStatus = '/sync/status';

  // ── Upload file privat (signed URL) ────────────────────────────
  static const String uploadFile = '/files/upload';

  static int _id(int value) {
    if (value <= 0) throw ArgumentError.value(value, 'id', 'must be positive');
    return value;
  }
}
