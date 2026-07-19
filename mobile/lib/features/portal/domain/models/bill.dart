class Bill {
  final int id;
  final String billNumber;
  final String period;
  final double amount;
  final double adminFee;
  final double totalAmount;
  final double previousReading;
  final double currentReading;
  final double usage;
  final String status;
  final DateTime dueDate;
  final DateTime billDate;
  final DateTime? paidDate;
  final String? paymentMethod;
  final bool isLate;
  final double? lateFee;
  final List<BillDetailItem> items;

  const Bill({
    required this.id,
    required this.billNumber,
    required this.period,
    required this.amount,
    required this.adminFee,
    required this.totalAmount,
    required this.previousReading,
    required this.currentReading,
    required this.usage,
    required this.status,
    required this.dueDate,
    required this.billDate,
    this.paidDate,
    this.paymentMethod,
    this.isLate = false,
    this.lateFee,
    this.items = const [],
  });

  factory Bill.fromJson(Map<String, dynamic> json) {
    return Bill(
      id: json['id'] as int,
      billNumber: json['bill_number'] as String,
      period: json['period'] as String,
      amount: (json['amount'] as num).toDouble(),
      adminFee: (json['admin_fee'] as num?)?.toDouble() ?? 0,
      totalAmount: (json['total_amount'] as num).toDouble(),
      previousReading: (json['previous_reading'] as num).toDouble(),
      currentReading: (json['current_reading'] as num).toDouble(),
      usage: (json['usage'] as num).toDouble(),
      status: json['status'] as String,
      dueDate: DateTime.parse(json['due_date'] as String),
      billDate: DateTime.parse(json['bill_date'] as String),
      paidDate: json['paid_date'] != null
          ? DateTime.parse(json['paid_date'] as String)
          : null,
      paymentMethod: json['payment_method'] as String?,
      isLate: json['is_late'] as bool? ?? false,
      lateFee: (json['late_fee'] as num?)?.toDouble(),
      items: json['items'] != null
          ? (json['items'] as List)
              .map((e) => BillDetailItem.fromJson(e as Map<String, dynamic>))
              .toList()
          : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'bill_number': billNumber,
      'period': period,
      'amount': amount,
      'admin_fee': adminFee,
      'total_amount': totalAmount,
      'previous_reading': previousReading,
      'current_reading': currentReading,
      'usage': usage,
      'status': status,
      'due_date': dueDate.toIso8601String(),
      'bill_date': billDate.toIso8601String(),
      'paid_date': paidDate?.toIso8601String(),
      'payment_method': paymentMethod,
      'is_late': isLate,
      'late_fee': lateFee,
      'items': items.map((e) => e.toJson()).toList(),
    };
  }
}

class BillDetailItem {
  final String label;
  final double amount;
  final String type;

  const BillDetailItem({
    required this.label,
    required this.amount,
    this.type = 'charge',
  });

  factory BillDetailItem.fromJson(Map<String, dynamic> json) {
    return BillDetailItem(
      label: json['label'] as String,
      amount: (json['amount'] as num).toDouble(),
      type: json['type'] as String? ?? 'charge',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'label': label,
      'amount': amount,
      'type': type,
    };
  }
}
