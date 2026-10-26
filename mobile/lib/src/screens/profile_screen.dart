import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/session_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/app_widgets.dart';
import 'hotel_screen.dart';
import 'payments_screen.dart';
import 'security_screen.dart';
import 'users_screen.dart';

void _open(BuildContext context, Widget screen) {
  Navigator.of(context).push(
    MaterialPageRoute<void>(builder: (_) => screen),
  );
}

/// The signed-in user's own details plus sign-out.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<SessionController>();
    final user = session.user;

    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            const SizedBox(height: 8),
            CircleAvatar(
              radius: 40,
              backgroundColor: AppTheme.charcoal,
              child: Text(
                user?.initials ?? '?',
                style: const TextStyle(
                  fontSize: 28,
                  color: AppTheme.gold,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(height: 12),
            Text(
              user?.name ?? 'Guest',
              style: AppTheme.display(20),
            ),
            const SizedBox(height: 4),
            Text(
              user?.email ?? '',
              style: const TextStyle(color: AppTheme.muted),
            ),
            if (user?.phone != null && user!.phone!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                user.phone!,
                style: const TextStyle(color: AppTheme.muted),
              ),
            ],
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              children: (user?.roles ?? const <String>[])
                  .map(
                    (role) => Chip(
                      label: Text(role),
                      backgroundColor: AppTheme.charcoal.withAlpha(12),
                      side: const BorderSide(color: AppTheme.border),
                    ),
                  )
                  .toList(growable: false),
            ),
            const SizedBox(height: 24),
            Panel(
              child: Column(
                children: [
                  _Row(
                    icon: Icons.badge_outlined,
                    label: 'Account type',
                    value: (user?.isStaff ?? false) ? 'Staff' : 'Guest',
                  ),
                  const Divider(height: 20),
                  _Row(
                    icon: Icons.alternate_email,
                    label: 'Email',
                    value: user?.email ?? '-',
                  ),
                  if (user?.guestProfileId != null) ...[
                    const Divider(height: 20),
                    _Row(
                      icon: Icons.confirmation_number_outlined,
                      label: 'Guest profile',
                      value: '#${user!.guestProfileId}',
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 24),
            const Align(
              alignment: Alignment.centerLeft,
              child: Text('SETTINGS & SHORTCUTS', style: AppTheme.eyebrow),
            ),
            const SizedBox(height: 10),
            ActionTile(
              icon: Icons.verified_user_outlined,
              title: 'Security',
              subtitle: 'Authenticator app and sign-in protection',
              onTap: () => _open(context, const SecurityScreen()),
            ),
            const SizedBox(height: 10),
            ActionTile(
              icon: Icons.storefront_outlined,
              title: 'Hotel website',
              subtitle: 'Rooms, gallery and contact details',
              onTap: () => _open(context, const HotelScreen()),
            ),
            if (user?.isStaff ?? false) ...[
              const SizedBox(height: 10),
              ActionTile(
                icon: Icons.payments_outlined,
                title: 'Payments',
                subtitle: 'Takings, pending balances and refunds',
                onTap: () => _open(context, const PaymentsScreen()),
              ),
            ],
            if (user?.isAdmin ?? false) ...[
              const SizedBox(height: 10),
              ActionTile(
                icon: Icons.manage_accounts_outlined,
                title: 'Users',
                subtitle: 'Staff and guest accounts',
                onTap: () => _open(context, const UsersScreen()),
              ),
            ],
            const SizedBox(height: 24),
            OutlinedButton.icon(
              onPressed: session.busy
                  ? null
                  : () => context.read<SessionController>().signOut(),
              icon: const Icon(Icons.logout, size: 18),
              label: const Text('Sign out'),
            ),
          ],
        ),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 18, color: AppTheme.gold),
        const SizedBox(width: 10),
        Text(label, style: const TextStyle(color: AppTheme.muted)),
        const Spacer(),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    );
  }
}
