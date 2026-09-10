/// WorkOrder model mobile (FSM — selaras backend WorkOrderController).
class WorkOrderModel {
  const WorkOrderModel({
    required this.id,
    required this.woNumber,
    required this.type,
    required this.priority,
    required this.status,
    required this.address,
    this.zoneId,
    this.sourceType,
    this.sourceId,
    this.latitude,
    this.longitude,
    this.description,
    this.slaDueAt,
    this.startedAt,
    this.completedAt,
    this.resolution,
    this.assignedToName,
    this.createdAt,
  });

  final int id;
  final String woNumber;
  final String type;
  final String priority;
  final String status;
  final String address;
  final int? zoneId;
  final String? sourceType;
  final int? sourceId;
  final double? latitude;
  final double? longitude;
  final String? description;
  final String? slaDueAt;
  final String? startedAt;
  final String? completedAt;
  final String? resolution;
  final String? assignedToName;
  final String? createdAt;

  bool get isGeo => latitude != null && longitude != null;
  int? get gisFeatureId => sourceType == 'gis_feature' ? sourceId : null;

  bool get canStart => status == 'open' || status == 'assigned';
  bool get canComplete => status == 'in_progress';

  String get typeLabel => switch (type) {
        'repair' => 'Perbaikan pipa',
        'leakage' => 'Kebocoran',
        'inspection' => 'Inspeksi',
        'installation' => 'Pemasangan',
        'meter_change' => 'Ganti meter',
        'disconnect' => 'Putus sambung',
        'reconnect' => 'Sambung kembali',
        'complaint' => 'Pengaduan',
        _ => type,
      };

  String get statusLabel => switch (status) {
        'open' => 'Baru',
        'assigned' => 'Ditugaskan',
        'in_progress' => 'Dikerjakan',
        'completed' => 'Selesai',
        'verified' => 'Terverifikasi',
        'cancelled' => 'Batal',
        _ => status,
      };

  factory WorkOrderModel.fromJson(Map<String, dynamic> json) {
    return WorkOrderModel(
      id: (json['id'] as num).toInt(),
      woNumber: json['wo_number'] as String? ?? '',
      type: json['type'] as String? ?? '',
      priority: json['priority'] as String? ?? 'medium',
      status: json['status'] as String? ?? 'open',
      address: json['address'] as String? ?? '-',
      zoneId: (json['zone_id'] as num?)?.toInt(),
      sourceType: json['source_type'] as String?,
      sourceId: (json['source_id'] as num?)?.toInt(),
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      description: json['description'] as String?,
      slaDueAt: json['sla_due_at'] as String?,
      startedAt: json['started_at'] as String?,
      completedAt: json['completed_at'] as String?,
      resolution: json['resolution'] as String?,
      assignedToName: (json['assigned_to'] is Map)
          ? (json['assigned_to'] as Map)['name'] as String?
          : null,
      createdAt: json['created_at'] as String?,
    );
  }
}
