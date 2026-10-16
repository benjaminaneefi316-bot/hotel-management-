import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// Shown while the app restores a stored session.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.navy,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(
                color: Colors.white.withAlpha(20),
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: AppTheme.gold.withAlpha(160)),
              ),
              child: const Icon(Icons.hotel, color: AppTheme.gold, size: 42),
            ),
            const SizedBox(height: 22),
            const Text(
              'Azure Bay Hotel',
              style: TextStyle(
                color: Colors.white,
                fontSize: 22,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.4,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Hospitality management suite',
              style: TextStyle(color: Colors.white70, fontSize: 13),
            ),
            const SizedBox(height: 32),
            const SizedBox(
              height: 26,
              width: 26,
              child: CircularProgressIndicator(
                strokeWidth: 2.4,
                valueColor: AlwaysStoppedAnimation<Color>(AppTheme.gold),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
