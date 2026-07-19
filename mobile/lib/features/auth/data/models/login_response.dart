/// Model login — SELARAS dengan respons backend `AuthController@login`.
/// Backend mengembalikan bentuk FLAT (bukan {success, data}):
///   { "token": "...", "user": {...}, "organization": {...} }
/// Parsing dilakukan manual (tanpa json_serializable) agar tidak perlu
/// menjalankan build_runner.
library;

class LoginResponse {
  final bool success;
  final String message;
  final LoginData? data;

  const LoginResponse({
    required this.success,
    required this.message,
    this.data,
  });

  /// Menerima respons flat backend. Jika ada `token`, dianggap sukses.
  factory LoginResponse.fromJson(Map<String, dynamic> json) {
    final hasToken = json['token'] != null;
    if (!hasToken) {
      return LoginResponse(
        success: false,
        message: (json['message'] as String?) ??
            ((json['error'] as Map<String, dynamic>?)?['message'] as String?) ??
            'Login gagal',
        data: null,
      );
    }
    return LoginResponse(
      success: true,
      message: (json['message'] as String?) ?? 'Login berhasil',
      data: LoginData.fromJson(json),
    );
  }
}

class LoginData {
  final String token;

  /// Sanctum memakai token yang sama untuk refresh (endpoint /refresh-token
  /// hanya butuh bearer token aktif). Simpan token sebagai refreshToken juga.
  final String refreshToken;
  final UserData user;
  final List<String> roles;
  final OrganizationData? organization;

  const LoginData({
    required this.token,
    required this.refreshToken,
    required this.user,
    required this.roles,
    this.organization,
  });

  factory LoginData.fromJson(Map<String, dynamic> json) {
    final token = json['token'] as String;
    final userJson = (json['user'] as Map<String, dynamic>?) ?? const {};
    final roles =
        (userJson['roles'] as List?)?.map((e) => e.toString()).toList() ??
            (json['roles'] as List?)?.map((e) => e.toString()).toList() ??
            <String>[];

    return LoginData(
      token: token,
      refreshToken: token,
      user: UserData.fromJson(userJson),
      roles: roles,
      organization: json['organization'] != null
          ? OrganizationData.fromJson(
              json['organization'] as Map<String, dynamic>,
            )
          : null,
    );
  }
}

class UserData {
  final int id;
  final String name;
  final String email;
  final bool isTenantAdmin;
  final String? phone;
  final String? avatarUrl;
  final String? customerNumber;
  final String? employeeNumber;
  final String? department;

  const UserData({
    required this.id,
    required this.name,
    required this.email,
    this.isTenantAdmin = false,
    this.phone,
    this.avatarUrl,
    this.customerNumber,
    this.employeeNumber,
    this.department,
  });

  factory UserData.fromJson(Map<String, dynamic> json) {
    return UserData(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: (json['name'] as String?) ?? '',
      email: (json['email'] as String?) ?? '',
      isTenantAdmin: (json['is_tenant_admin'] as bool?) ?? false,
      phone: json['phone'] as String?,
      avatarUrl: json['avatar_url'] as String?,
      customerNumber: json['customer_number'] as String?,
      employeeNumber: json['employee_number'] as String?,
      department: json['department'] as String?,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'is_tenant_admin': isTenantAdmin,
        'phone': phone,
        'avatar_url': avatarUrl,
        'customer_number': customerNumber,
        'employee_number': employeeNumber,
        'department': department,
      };
}

class OrganizationData {
  final int id;
  final String code;
  final String name;

  const OrganizationData({
    required this.id,
    required this.code,
    required this.name,
  });

  factory OrganizationData.fromJson(Map<String, dynamic> json) {
    return OrganizationData(
      id: (json['id'] as num?)?.toInt() ?? 0,
      code: (json['code'] as String?) ?? '',
      name: (json['name'] as String?) ?? '',
    );
  }
}
