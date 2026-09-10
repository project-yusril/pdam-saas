import '../../../../api/api_services.dart';
import '../../../../core/network/api_client.dart';
import '../../../../core/utils/gps_helper.dart';

/// LocationRemoteSource — lapor titik petugas ke peta GIS kantor
/// (POST /field/location → technician_locations → layer "Petugas LIVE" +
/// dispatch WO terdekat). GPS & permission ditangani [GpsHelper] (satu jalur
/// dengan form survey & baca meter).
class LocationRemoteSource {
  LocationRemoteSource({required ApiClient apiClient})
      : _api = FieldApi(apiClient);

  final FieldApi _api;

  /// Ambil GPS terkini lalu kirim; kembalikan koordinat + akurasi utk snackbar.
  Future<({double latitude, double longitude, double accuracy})>
      captureAndReport() async {
    final pos = await GpsHelper.getCurrentPosition();
    await _api.reportLocation(
      latitude: pos.latitude,
      longitude: pos.longitude,
      accuracy: pos.accuracy,
    );

    return (
      latitude: pos.latitude,
      longitude: pos.longitude,
      accuracy: pos.accuracy,
    );
  }
}
