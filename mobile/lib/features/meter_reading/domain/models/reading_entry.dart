class ReadingEntry {
  final int id;
  final int taskId;
  final String customerName;
  final String customerNumber;
  final String address;
  final double? previousReading;
  final double? currentReading;
  final double? usage;
  final String? meterPhoto;
  final String? housePhoto;
  final double? latitude;
  final double? longitude;
  final String? notes;
  final String status;
  final DateTime? readAt;
  final bool synced;

  const ReadingEntry({
    required this.id,
    required this.taskId,
    required this.customerName,
    required this.customerNumber,
    required this.address,
    this.previousReading,
    this.currentReading,
    this.usage,
    this.meterPhoto,
    this.housePhoto,
    this.latitude,
    this.longitude,
    this.notes,
    required this.status,
    this.readAt,
    this.synced = false,
  });

  factory ReadingEntry.fromJson(Map<String, dynamic> json) {
    return ReadingEntry(
      id: json['id'] as int,
      taskId: json['task_id'] as int,
      customerName: json['customer_name'] as String,
      customerNumber: json['customer_number'] as String,
      address: json['address'] as String,
      previousReading: (json['previous_reading'] as num?)?.toDouble(),
      currentReading: (json['current_reading'] as num?)?.toDouble(),
      usage: (json['usage'] as num?)?.toDouble(),
      meterPhoto: json['meter_photo'] as String?,
      housePhoto: json['house_photo'] as String?,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      notes: json['notes'] as String?,
      status: json['status'] as String,
      readAt: json['read_at'] != null
          ? DateTime.parse(json['read_at'] as String)
          : null,
      synced: json['synced'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'task_id': taskId,
      'customer_name': customerName,
      'customer_number': customerNumber,
      'address': address,
      'previous_reading': previousReading,
      'current_reading': currentReading,
      'usage': usage,
      'meter_photo': meterPhoto,
      'house_photo': housePhoto,
      'latitude': latitude,
      'longitude': longitude,
      'notes': notes,
      'status': status,
      'read_at': readAt?.toIso8601String(),
      'synced': synced,
    };
  }
}
