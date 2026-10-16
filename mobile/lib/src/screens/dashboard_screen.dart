import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/hotel_repository.dart';
import '../models/dashboard_summary.dart';
import '../state/session_controller.dart';
import '../theme/app_theme.dart';
import '../util/formatters.dart';
import '../widgets/app_widgets.dart';

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

  @override
  Widget build(BuildContext context) {
    final user = context.watch<SessionController>().user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
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
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.navy,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  summary.date.isEmpty ? 'Today' : summary.date,
                  style: const TextStyle(color: AppTheme.muted),
                ),
                const SizedBox(height: 16),
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
                    ),
                    KpiTile(
                      icon: Icons.logout,
                      label: 'Departures today',
                      value: '${summary.departuresToday}',
                    ),
                    KpiTile(
                      icon: Icons.king_bed_outlined,
                      label: 'In house',
                      value: '${summary.inHouse}',
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
                      color: AppTheme.navy,
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
                                      color: AppTheme.navy,
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
