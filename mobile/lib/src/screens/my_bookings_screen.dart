import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/hotel_repository.dart';
import '../models/booking.dart';
import '../state/session_controller.dart';
import '../theme/app_theme.dart';
import '../util/formatters.dart';
import '../widgets/app_widgets.dart';
import 'booking_detail_screen.dart';

/// Lists bookings: every booking for staff, only the caller's for a guest.
class MyBookingsScreen extends StatefulWidget {
  const MyBookingsScreen({super.key});

  @override
  State<MyBookingsScreen> createState() => _MyBookingsScreenState();
}

class _MyBookingsScreenState extends State<MyBookingsScreen> {
  static const List<({String value, String label})> _filters = [
    (value: '', label: 'All'),
    (value: 'pending', label: 'Pending'),
    (value: 'confirmed', label: 'Confirmed'),
    (value: 'checked_in', label: 'In house'),
    (value: 'checked_out', label: 'Departed'),
    (value: 'cancelled', label: 'Cancelled'),
  ];

  String _status = '';
  late Future<List<Booking>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Booking>> _load() =>
      context.read<HotelRepository>().bookings(status: _status.isEmpty ? null : _status);

  void _reload() => setState(() => _future = _load());

  void _setStatus(String status) {
    setState(() {
      _status = status;
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final isStaff = context.select<SessionController, bool>((s) => s.isStaff);

    return Scaffold(
      appBar: AppBar(
        title: Text(isStaff ? 'All bookings' : 'My bookings'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: Column(
        children: [
          SizedBox(
            height: 54,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              children: _filters
                  .map(
                    (f) => Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(f.label),
                        selected: _status == f.value,
                        onSelected: (_) => _setStatus(f.value),
                      ),
                    ),
                  )
                  .toList(growable: false),
            ),
          ),
          Expanded(
            child: FutureBuilder<List<Booking>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  return EmptyState(
                    icon: Icons.wifi_off,
                    title: 'Could not load bookings',
                    message: '${snapshot.error}',
                    onRetry: _reload,
                  );
                }

                final bookings = snapshot.data ?? const <Booking>[];
                if (bookings.isEmpty) {
                  return const EmptyState(
                    icon: Icons.event_busy,
                    title: 'No bookings here',
                    message: 'Reservations will appear in this list.',
                  );
                }

                return RefreshIndicator(
                  onRefresh: () async => _reload(),
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    itemCount: bookings.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, index) => _BookingTile(
                      booking: bookings[index],
                      showGuest: isStaff,
                      onChanged: _reload,
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _BookingTile extends StatelessWidget {
  const _BookingTile({
    required this.booking,
    required this.showGuest,
    required this.onChanged,
  });

  final Booking booking;
  final bool showGuest;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final color = StatusPill.colorFor(booking.status);

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () async {
          await Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => BookingDetailScreen(bookingId: booking.id),
            ),
          );
          onChanged();
        },
        child: Container(
          decoration: AppTheme.panel(),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      booking.reference,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        color: AppTheme.charcoal,
                      ),
                    ),
                  ),
                  StatusPill(label: booking.statusLabel, color: color),
                ],
              ),
              const SizedBox(height: 10),
              if (showGuest && booking.guest != null) ...[
                Row(
                  children: [
                    const Icon(Icons.person_outline,
                        size: 15, color: AppTheme.muted),
                    const SizedBox(width: 4),
                    Text(
                      booking.guest!.fullName,
                      style: const TextStyle(
                        color: AppTheme.text,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
              ],
              Row(
                children: [
                  const Icon(Icons.calendar_month_outlined,
                      size: 15, color: AppTheme.muted),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      '${Fmt.prettyDate(booking.checkInDate)} â†’ '
                      '${Fmt.prettyDate(booking.checkOutDate)}',
                      style: const TextStyle(color: AppTheme.muted, fontSize: 13),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  const Icon(Icons.bed_outlined, size: 15, color: AppTheme.muted),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      booking.rooms.isEmpty
                          ? 'No room assigned yet'
                          : booking.rooms
                              .map((r) => r.roomNumber)
                              .join(', '),
                      style: const TextStyle(color: AppTheme.muted, fontSize: 13),
                    ),
                  ),
                  Text(
                    Fmt.money(booking.totalAmount),
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: AppTheme.charcoal,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
