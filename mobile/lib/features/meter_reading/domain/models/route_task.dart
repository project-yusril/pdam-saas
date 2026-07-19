class RouteTask {
  final int id;
  final String routeName;
  final String area;
  final int totalCustomers;
  final int completedCount;
  final int pendingCount;
  final int problemCount;
  final DateTime date;
  final String status;

  const RouteTask({
    required this.id,
    required this.routeName,
    required this.area,
    required this.totalCustomers,
    required this.completedCount,
    required this.pendingCount,
    required this.problemCount,
    required this.date,
    required this.status,
  });

  double get completionPercentage {
    if (totalCustomers == 0) return 0;
    return completedCount / totalCustomers;
  }

  factory RouteTask.fromJson(Map<String, dynamic> json) {
    return RouteTask(
      id: json['id'] as int,
      routeName: json['route_name'] as String,
      area: json['area'] as String,
      totalCustomers: json['total_customers'] as int,
      completedCount: json['completed_count'] as int,
      pendingCount: json['pending_count'] as int,
      problemCount: json['problem_count'] as int,
      date: DateTime.parse(json['date'] as String),
      status: json['status'] as String,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'route_name': routeName,
      'area': area,
      'total_customers': totalCustomers,
      'completed_count': completedCount,
      'pending_count': pendingCount,
      'problem_count': problemCount,
      'date': date.toIso8601String(),
      'status': status,
    };
  }
}
