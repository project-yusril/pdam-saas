class AppConstants {
  AppConstants._();

  static const String appName = 'PDAM Mobile';
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://api.pdam.go.id/api/v1',
  );
  static const String apiDevBaseUrl = String.fromEnvironment(
    'API_DEV_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );
  static const int connectTimeoutMs = 30000;
  static const int receiveTimeoutMs = 30000;
  static const int sendTimeoutMs = 30000;

  static const String authTokenKey = 'auth_token';
  static const String refreshTokenKey = 'refresh_token';
  static const String userRoleKey = 'user_role';
  static const String userDataKey = 'user_data';

  static const String roleCustomer = 'customer';
  static const String roleMeterOfficer = 'meter_officer';
  static const String roleSurveyOfficer = 'survey_officer';

  static const String cacheBoxMeterReadings = 'meter_readings_queue';
  static const String cacheBoxSurveyDrafts = 'survey_drafts';
  static const String cacheBoxCustomerBills = 'customer_bills';
  static const String cacheBoxNotifications = 'notifications';

  static const String fcmTopicAll = 'all_users';
  static const String fcmTopicCustomers = 'customers';
  static const String fcmTopicMeterOfficers = 'meter_officers';
  static const String fcmTopicSurveyOfficers = 'survey_officers';

  static const int maxRetryAttempts = 3;
  static const int retryDelaySeconds = 5;
}
