import 'package:flutter_test/flutter_test.dart';
import 'package:pdam_mobile/core/network/endpoints.dart';

void main() {
  test('KTP OCR uses the multipart upload endpoint', () {
    expect(Endpoints.ocrKtpUpload, '/prospects/upload-ktp');
  });

  test('endpoint builders produce tenant-neutral relative paths', () {
    expect(Endpoints.customerBillDetail(12), '/portal/bills/12');
    expect(Endpoints.customerBillPay(12), '/portal/bills/12/pay');
    expect(
        Endpoints.customerNotificationsRead(9), '/portal/notifications/9/read');
    expect(Endpoints.meterRouteTasks(4), '/meter-routes/4');
    expect(Endpoints.surveyTaskDetail(5), '/prospects/5');
    expect(Endpoints.surveySubmit(5), '/prospects/5/survey');
    expect(Endpoints.surveyReview(7), '/survey-reports/7/review');
  });

  test('endpoint builders reject zero IDs', () {
    expect(() => Endpoints.customerBillDetail(0), throwsArgumentError);
  });

  test('endpoint builders reject negative IDs', () {
    expect(() => Endpoints.surveySubmit(-1), throwsArgumentError);
  });

  test('paths contain neither tenant host nor unresolved placeholders', () {
    final paths = [
      Endpoints.customerBillDetail(1),
      Endpoints.customerNotificationsRead(1),
      Endpoints.meterRouteTasks(1),
      Endpoints.surveySubmit(1),
    ];
    expect(paths, everyElement(startsWith('/')));
    expect(paths, everyElement(isNot(contains('{'))));
    expect(paths, everyElement(isNot(contains('://'))));
  });

  test('meter submit collection path has no ignored reading ID contract', () {
    expect(Endpoints.meterReadingSubmit, '/meter-readings');
  });

  test('field location endpoint exists', () {
    expect(Endpoints.fieldLocation, '/field/location');
  });

  test('work orders mine endpoint has correct base path', () {
    expect(Endpoints.workOrders, '/work-orders');
    // WorkApi uses query params mine=1 — not an additional endpoint
  });
}
