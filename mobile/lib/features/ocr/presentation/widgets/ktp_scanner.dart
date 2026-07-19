import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../../core/network/api_client.dart';
import '../../../../core/network/endpoints.dart';
import '../../../../core/theme/app_colors.dart';
import '../../data/ktp_response_parser.dart';
import 'ktp_review_confirmation.dart';

class KtpScanner extends StatefulWidget {
  final void Function(Map<String, String> ktpData) onParsed;

  const KtpScanner({super.key, required this.onParsed});

  @override
  State<KtpScanner> createState() => _KtpScannerState();
}

class _KtpScannerState extends State<KtpScanner> {
  final ImagePicker _picker = ImagePicker();
  final ApiClient _api = ApiClient();
  XFile? _image;
  bool _isLoading = false;
  Map<String, String>? _parsedData;
  String? _error;

  Future<void> _pickImage() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text('Kamera'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Galeri'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;
    final image = await _picker.pickImage(source: source, imageQuality: 85);
    if (image != null) await _processImage(image);
  }

  Future<void> _processImage(XFile image) async {
    setState(() {
      _image = image;
      _isLoading = true;
      _parsedData = null;
      _error = null;
    });

    try {
      final response = await _api.uploadFile<Map<String, dynamic>>(
        Endpoints.ocrKtpUpload,
        formData: FormData.fromMap({
          'ktp_file': await MultipartFile.fromFile(image.path),
        }),
      );
      final normalized = KtpResponseParser.parse(response.data);

      if (!mounted) return;
      setState(() => _parsedData = normalized);
    } catch (exception) {
      if (!mounted) return;
      setState(() => _error = 'OCR gagal. Pastikan foto jelas lalu coba lagi.');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final confidence = double.tryParse(_parsedData?['confidence'] ?? '');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        InkWell(
          onTap: _isLoading ? null : _pickImage,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            height: 220,
            decoration: BoxDecoration(
              color: AppColors.surfaceVariant,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.outline),
            ),
            child: _image == null
                ? const Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.credit_card,
                          size: 48, color: AppColors.primary),
                      SizedBox(height: 12),
                      Text('Ketuk untuk mengambil foto KTP'),
                      Text('Data wajib diperiksa sebelum disimpan'),
                    ],
                  )
                : Stack(
                    fit: StackFit.expand,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child:
                            Image.file(File(_image!.path), fit: BoxFit.cover),
                      ),
                      if (_isLoading)
                        const ColoredBox(
                          color: Colors.black54,
                          child: Center(child: CircularProgressIndicator()),
                        ),
                    ],
                  ),
          ),
        ),
        if (_error != null) ...[
          const SizedBox(height: 8),
          Text(_error!, style: const TextStyle(color: AppColors.error)),
        ],
        if (_parsedData != null) ...[
          const SizedBox(height: 12),
          const Text(
            'OCR selesai. Periksa dan koreksi semua data sebelum submit.',
            style: TextStyle(
                color: AppColors.success, fontWeight: FontWeight.w600),
          ),
          if (confidence != null)
            Text('Confidence OCR: ${(confidence * 100).round()}%'),
          const SizedBox(height: 8),
          KtpReviewConfirmation(
            initialData: _parsedData!,
            onConfirmed: widget.onParsed,
          ),
        ],
      ],
    );
  }
}
