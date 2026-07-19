import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

class LoadingSkeleton extends StatelessWidget {
  const LoadingSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Shimmer.fromColors(
        baseColor: Colors.grey.shade300,
        highlightColor: Colors.grey.shade100,
        child: Column(
          children: [
            _buildCardSkeleton(),
            const SizedBox(height: 16),
            _buildCardSkeleton(height: 120),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(child: _buildCardSkeleton(height: 100)),
                const SizedBox(width: 12),
                Expanded(child: _buildCardSkeleton(height: 100)),
              ],
            ),
            const SizedBox(height: 16),
            _buildListSkeleton(),
            _buildListSkeleton(),
            _buildListSkeleton(),
          ],
        ),
      ),
    );
  }

  Widget _buildCardSkeleton({double height = 80}) {
    return Container(
      height: height,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
      ),
    );
  }

  Widget _buildListSkeleton() {
    return Container(
      height: 72,
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
      ),
    );
  }
}

class LoadingDetailSkeleton extends StatelessWidget {
  const LoadingDetailSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: Colors.grey.shade300,
      highlightColor: Colors.grey.shade100,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 200,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            const SizedBox(height: 16),
            Container(height: 28, width: 200, color: Colors.white),
            const SizedBox(height: 8),
            Container(height: 16, width: 150, color: Colors.white),
            const SizedBox(height: 16),
            Container(
              height: 120,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
