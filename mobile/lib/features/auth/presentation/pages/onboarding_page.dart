import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_colors.dart';

class OnboardingPage extends StatefulWidget {
  const OnboardingPage({super.key});

  @override
  State<OnboardingPage> createState() => _OnboardingPageState();
}

class _OnboardingPageState extends State<OnboardingPage> {
  final PageController _pageController = PageController();
  int _currentPage = 0;

  final List<OnboardingContent> _contents = [
    const OnboardingContent(
      icon: Icons.receipt_long_rounded,
      title: 'Cek Tagihan Mudah',
      description:
          'Lihat tagihan air Anda kapan saja, bayar langsung dari aplikasi, dan pantau riwayat pembayaran.',
    ),
    const OnboardingContent(
      icon: Icons.water_drop_rounded,
      title: 'Pantau Pemakaian Air',
      description:
          'Grafik pemakaian air bulanan membantu Anda mengontrol konsumsi dan menghemat pengeluaran.',
    ),
    const OnboardingContent(
      icon: Icons.notifications_active_rounded,
      title: 'Notifikasi Real-Time',
      description:
          'Dapatkan notifikasi jatuh tempo tagihan, info pemadaman, dan pengumuman penting dari PDAM.',
    ),
  ];

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: PageView.builder(
                controller: _pageController,
                itemCount: _contents.length,
                onPageChanged: (index) {
                  setState(() {
                    _currentPage = index;
                  });
                },
                itemBuilder: (context, index) {
                  return _buildPage(_contents[index]);
                },
              ),
            ),
            _buildIndicator(),
            const SizedBox(height: 24),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Row(
                children: [
                  if (_currentPage < _contents.length - 1) ...[
                    TextButton(
                      onPressed: () {
                        _pageController.jumpToPage(_contents.length - 1);
                      },
                      child: const Text('LEWATI'),
                    ),
                    const Spacer(),
                    ElevatedButton(
                      onPressed: () {
                        _pageController.nextPage(
                          duration: const Duration(milliseconds: 300),
                          curve: Curves.easeInOut,
                        );
                      },
                      child: const Text('LANJUT'),
                    ),
                  ] else ...[
                    const Spacer(),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () {
                          context.go('/login');
                        },
                        child: const Text('MULAI'),
                      ),
                    ),
                    const Spacer(),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'PDAM v1.0',
              style: TextStyle(
                color: AppColors.onSurfaceVariant,
                fontSize: 12,
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildPage(OnboardingContent content) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 140,
            height: 140,
            decoration: BoxDecoration(
              color: AppColors.primaryContainer,
              borderRadius: BorderRadius.circular(70),
            ),
            child: Icon(
              content.icon,
              size: 72,
              color: AppColors.primary,
            ),
          ),
          const SizedBox(height: 48),
          Text(
            content.title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(height: 16),
          Text(
            content.description,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                  color: AppColors.onSurfaceVariant,
                  height: 1.5,
                ),
          ),
        ],
      ),
    );
  }

  Widget _buildIndicator() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(
        _contents.length,
        (index) => AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          margin: const EdgeInsets.symmetric(horizontal: 4),
          width: _currentPage == index ? 24 : 8,
          height: 8,
          decoration: BoxDecoration(
            color:
                _currentPage == index ? AppColors.primary : AppColors.outline,
            borderRadius: BorderRadius.circular(4),
          ),
        ),
      ),
    );
  }
}

class OnboardingContent {
  final IconData icon;
  final String title;
  final String description;

  const OnboardingContent({
    required this.icon,
    required this.title,
    required this.description,
  });
}
