class SurveyFormData {
  final int taskId;
  final String houseCondition;
  final String roadAccess;
  final double? pipeDistance;
  final String landStatus;
  final String? existingPipeSize;
  final String? waterSource;
  final double? houseLatitude;
  final double? houseLongitude;
  final List<String>? photos;
  final String recommendation;
  final String? notes;
  final List<SurveyChecklistItem>? checklist;

  const SurveyFormData({
    required this.taskId,
    required this.houseCondition,
    required this.roadAccess,
    this.pipeDistance,
    required this.landStatus,
    this.existingPipeSize,
    this.waterSource,
    this.houseLatitude,
    this.houseLongitude,
    this.photos,
    required this.recommendation,
    this.notes,
    this.checklist,
  });

  Map<String, dynamic> toJson() {
    return {
      'task_id': taskId,
      'house_condition': houseCondition,
      'road_access': roadAccess,
      'pipe_distance': pipeDistance,
      'land_status': landStatus,
      'existing_pipe_size': existingPipeSize,
      'water_source': waterSource,
      'house_latitude': houseLatitude,
      'house_longitude': houseLongitude,
      'photos': photos,
      'recommendation': recommendation,
      'notes': notes,
      'checklist': checklist?.map((e) => e.toJson()).toList(),
    };
  }

  factory SurveyFormData.fromJson(Map<String, dynamic> json) {
    return SurveyFormData(
      taskId: json['task_id'] as int,
      houseCondition: json['house_condition'] as String,
      roadAccess: json['road_access'] as String,
      pipeDistance: (json['pipe_distance'] as num?)?.toDouble(),
      landStatus: json['land_status'] as String,
      existingPipeSize: json['existing_pipe_size'] as String?,
      waterSource: json['water_source'] as String?,
      houseLatitude: (json['house_latitude'] as num?)?.toDouble(),
      houseLongitude: (json['house_longitude'] as num?)?.toDouble(),
      photos: json['photos'] != null
          ? List<String>.from(json['photos'] as List)
          : null,
      recommendation: json['recommendation'] as String,
      notes: json['notes'] as String?,
      checklist: json['checklist'] != null
          ? (json['checklist'] as List)
              .map((e) =>
                  SurveyChecklistItem.fromJson(e as Map<String, dynamic>))
              .toList()
          : null,
    );
  }
}

class SurveyChecklistItem {
  final String label;
  final bool checked;
  final String? note;

  const SurveyChecklistItem({
    required this.label,
    required this.checked,
    this.note,
  });

  factory SurveyChecklistItem.fromJson(Map<String, dynamic> json) {
    return SurveyChecklistItem(
      label: json['label'] as String,
      checked: json['checked'] as bool,
      note: json['note'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'label': label,
      'checked': checked,
      'note': note,
    };
  }

  SurveyChecklistItem copyWith({bool? checked, String? note}) {
    return SurveyChecklistItem(
      label: label,
      checked: checked ?? this.checked,
      note: note ?? this.note,
    );
  }
}
