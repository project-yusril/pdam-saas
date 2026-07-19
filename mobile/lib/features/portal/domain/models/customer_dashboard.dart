class CustomerDashboard {
  final String customerName;
  final String customerNumber;
  final String address;
  final double currentBillAmount;
  final String currentBillStatus;
  final String currentBillPeriod;
  final int daysUntilDue;
  final double totalUsage;
  final double averageDailyUsage;
  final int billHistoryCount;

  const CustomerDashboard({
    required this.customerName,
    required this.customerNumber,
    required this.address,
    required this.currentBillAmount,
    required this.currentBillStatus,
    required this.currentBillPeriod,
    required this.daysUntilDue,
    required this.totalUsage,
    required this.averageDailyUsage,
    required this.billHistoryCount,
  });

  factory CustomerDashboard.fromJson(Map<String, dynamic> json) {
    return CustomerDashboard(
      customerName: json['customer_name'] as String,
      customerNumber: json['customer_number'] as String,
      address: json['address'] as String,
      currentBillAmount: (json['current_bill_amount'] as num).toDouble(),
      currentBillStatus: json['current_bill_status'] as String,
      currentBillPeriod: json['current_bill_period'] as String,
      daysUntilDue: json['days_until_due'] as int,
      totalUsage: (json['total_usage'] as num).toDouble(),
      averageDailyUsage: (json['average_daily_usage'] as num).toDouble(),
      billHistoryCount: json['bill_history_count'] as int,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'customer_name': customerName,
      'customer_number': customerNumber,
      'address': address,
      'current_bill_amount': currentBillAmount,
      'current_bill_status': currentBillStatus,
      'current_bill_period': currentBillPeriod,
      'days_until_due': daysUntilDue,
      'total_usage': totalUsage,
      'average_daily_usage': averageDailyUsage,
      'bill_history_count': billHistoryCount,
    };
  }
}
