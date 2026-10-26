import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/hotel_repository.dart';
import '../models/dashboard_summary.dart';
import '../state/session_controller.dart';
import '../theme/app_theme.dart';
import '../util/formatters.dart';
import '../widgets/app_widgets.dart';
import 'front_desk_screen.dart';
import 'my_bookings_screen.dart';
import 'rooms_screen.dart';

/// Staff home: today's operational KPIs and a housekeeping breakdown.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late Future<DashboardSummary> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<DashboardSummary> _load() =>
      context.read<HotelRepository>().dashboardSummary();

  void _reload() => setState(() => _future = _load());

  Future<void> _open(Widget screen) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => screen),
    );
    if (mounted) {
      _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<SessionController>().user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: [
          TextButton.icon(
            onPressed: _reload,
            icon: const Icon(Icons.refresh, size: 20),
            label: const Text('Refresh'),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: FutureBuilder<DashboardSummary>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || !snapshot.hasData) {
            return EmptyState(
              icon: Icons.wifi_off,
              title: 'Could not load the dashboard',
              message: '${snapshot.error}',
              onRetry: _reload,
            );
          }

          final summary = snapshot.data!;

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  'Welcome back, ${user?.name ?? 'there'}',
                  style: AppTheme.display(22),
                ),
                const SizedBox(height: 2),
                Text(
                  summary.date.isEmpty ? 'Today' : summary.date,
                  style: const TextStyle(color: AppTheme.muted),
                ),
                const SizedBox(height: 16),
                Panel(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('TODAY AT THE FRONT DESK', style: AppTheme.eyebrow),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: _Count(
                              value: '${summary.arrivalsToday}',
                              label: 'Arriving',
                              icon: Icons.login,
                            ),
                          ),
                          Container(
                            width: 1,
                            height: 42,
                            color: AppTheme.border,
                          ),
                          Expanded(
                            child: _Count(
                              value: '${summary.departuresToday}',
                              label: 'Leaving',
                              icon: Icons.logout,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: FilledButton.icon(
                          onPressed: () => _open(const FrontDeskScreen()),
                          icon: const Icon(Icons.support_agent, size: 21),
                          label: const Text('Open front desk'),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                const Text('QUICK ACTIONS', style: AppTheme.eyebrow),
                const SizedBox(height: 10),
                ActionTile(
                  icon: Icons.event_note,
                  title: 'All bookings',
                  subtitle: 'Search, open and cancel reservations',
                  onTap: () => _open(const MyBookingsScreen()),
                ),
                const SizedBox(height: 10),
                ActionTile(
                  icon: Icons.meeting_room_outlined,
                  title: 'Rooms',
                  subtitle: 'See every room and its housekeeping status',
                  onTap: () => _open(const RoomsScreen()),
                ),
                const SizedBox(height: 22),
                const Text('TODAY AT A GLANCE', style: AppTheme.eyebrow),
                const SizedBox(height: 10),
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                  childAspectRatio: 1.35,
                  children: [
                    KpiTile(
                      icon: Icons.login,
                      label: 'Arrivals today',
                      value: '${summary.arrivalsToday}',
                      onTap: () => _open(const FrontDeskScreen()),
                    ),
                    KpiTile(
                      icon: Icons.logout,
                      label: 'Departures today',
                      value: '${summary.departuresToday}',
                      onTap: () => _open(const FrontDeskScreen()),
                    ),
                    KpiTile(
                      icon: Icons.king_bed_outlined,
                      label: 'In house',
                      value: '${summary.inHouse}',
                      onTap: () => _open(const MyBookingsScreen()),
                    ),
                    KpiTile(
                      icon: Icons.pie_chart_outline,
                      label: 'Occupancy',
                      value: '${summary.occupancyPercent.toStringAsFixed(1)}%',
                    ),
                    KpiTile(
                      icon: Icons.payments_outlined,
                      label: 'Revenue (month)',
                      value: Fmt.money(summary.revenueMonth),
                    ),
                    KpiTile(
                      icon: Icons.hourglass_bottom,
                      label: 'Pending bookings',
                      value: '${summary.pendingBookings}',
                      onTap: () => _open(const MyBookingsScreen()),
                    ),
                  ],
                ),
                if (summary.rooms.isNotEmpty) ...[
                  const SizedBox(height: 20),
                  const Text(
                    'Rooms by housekeeping status',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: AppTheme.charcoal,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Panel(
                    child: Column(
                      children: summary.rooms
                          .map(
                            (row) => Padding(
                              padding: const EdgeInsets.symmetric(vertical: 6),
                              child: Row(
                                children: [
                                  StatusPill(
                                    label: row.label,
                                    color: StatusPill.colorFor(row.status),
                                  ),
                                  const Spacer(),
                                  Text(
                                    '${row.count}',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: AppTheme.charcoal,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          )
                          .toList(growable: false),
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _Count extends StatelessWidget {
  const _Count({required this.value, required this.label, required this.icon});

  final String value;
  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Icon(icon, color: AppTheme.gold, size: 20),
        const SizedBox(height: 4),
        Text(value, style: AppTheme.display(26, weight: FontWeight.w700)),
        Text(
          label,
          style: const TextStyle(
            color: AppTheme.muted,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}
