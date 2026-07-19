class SurveyTask {
  final int id;
  final String applicantName;
  final String applicantPhone;
  final String address;
  final String surveyType;
  final double? latitude;
  final double? longitude;
  final String status;
  final String? notes;
  final DateTime createdAt;
  final DateTime? scheduledDate;
  final DateTime? completedAt;

  const SurveyTask({
    required this.id,
    required this.applicantName,
    required this.applicantPhone,
    required this.address,
    required this.surveyType,
    this.latitude,
    this.longitude,
    required this.status,
    this.notes,
    required this.createdAt,
    this.scheduledDate,
    this.completedAt,
  });

  String get surveyTypeLabel {
    switch (surveyType.toLowerCase()) {
      case 'new_connection':
        return 'Sambungan Baru';
      case 'relocation':
        return 'Pemindahan';
      case 'upgrade':
        return 'Peningkatan';
      case 'complaint':
        return 'Pengaduan';
      default:
        return surveyType;
    }
  }

  factory SurveyTask.fromJson(Map<String, dynamic> json) {
    return SurveyTask(
      id: json['id'] as int,
      applicantName: json['applicant_name'] as String,
      applicantPhone: json['applicant_phone'] as String,
      address: json['address'] as String,
      surveyType: json['survey_type'] as String,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      status: json['status'] as String,
      notes: json['notes'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      scheduledDate: json['scheduled_date'] != null
          ? DateTime.parse(json['scheduled_date'] as String)
          : null,
      completedAt: json['completed_at'] != null
          ? DateTime.parse(json['completed_at'] as String)
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'applicant_name': applicantName,
      'applicant_phone': applicantPhone,
      'address': address,
      'survey_type': surveyType,
      'latitude': latitude,
      'longitude': longitude,
      'status': status,
      'notes': notes,
      'created_at': createdAt.toIso8601String(),
      'scheduled_date': scheduledDate?.toIso8601String(),
      'completed_at': completedAt?.toIso8601String(),
    };
  }
}
