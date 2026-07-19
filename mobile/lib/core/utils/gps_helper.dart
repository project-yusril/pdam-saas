import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import '../errors/exceptions.dart';

class GpsHelper {
  GpsHelper._();

  static const double defaultLatitude = -6.1750;
  static const double defaultLongitude = 106.8280;
  static const double defaultZoom = 15.0;

  static Future<Position> getCurrentPosition() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw const PermissionException(
        message: 'Layanan GPS tidak aktif. Harap nyalakan GPS.',
      );
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        throw const PermissionException(
          message:
              'Izin lokasi ditolak. Harap aktifkan izin lokasi di pengaturan.',
        );
      }
    }

    if (permission == LocationPermission.deniedForever) {
      throw const PermissionException(
        message:
            'Izin lokasi dinonaktifkan permanen. Harap aktifkan di pengaturan aplikasi.',
      );
    }

    return Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
      timeLimit: const Duration(seconds: 15),
    );
  }

  static LatLng toLatLng(double latitude, double longitude) {
    return LatLng(latitude, longitude);
  }

  static double calculateDistance(LatLng point1, LatLng point2) {
    final distanceInMeters =
        const Distance().as(LengthUnit.Meter, point1, point2);
    return distanceInMeters;
  }

  static String formatDistance(double meters) {
    if (meters >= 1000) {
      return '${(meters / 1000).toStringAsFixed(1)} km';
    }
    return '${meters.round()} m';
  }

  static String formatCoordinate(double value) {
    return value.toStringAsFixed(6);
  }

  static String buildGmapsUrl(double lat, double lng, {String? label}) {
    final buffer = StringBuffer('https://www.google.com/maps/search/?api=1');
    buffer.write('&query=$lat,$lng');
    if (label != null) {
      buffer.write('($label)');
    }
    return buffer.toString();
  }

  static String buildGmapsDirectionsUrl(double lat, double lng,
      {String? label}) {
    final buffer = StringBuffer('https://www.google.com/maps/dir/?api=1');
    buffer.write('&destination=$lat,$lng');
    if (label != null) {
      buffer.write('($label)');
    }
    return buffer.toString();
  }

  static bool isWithinRadius(LatLng center, LatLng point, double radiusMeters) {
    final distance = calculateDistance(center, point);
    return distance <= radiusMeters;
  }

  static String formatLocationInfo(Position position) {
    return '${formatCoordinate(position.latitude)}, ${formatCoordinate(position.longitude)} '
        '(\u00B1${position.accuracy.toStringAsFixed(0)}m)';
  }
}
