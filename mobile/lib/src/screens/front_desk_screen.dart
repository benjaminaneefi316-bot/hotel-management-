import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_exception.dart';
import '../api/hotel_repository.dart';
import '../models/booking.dart';
import '../models/front_desk.dart';
import '../theme/app_theme.dart';
import '../util/formatters.dart';
import '../widgets/app_widgets.dart';

/// Today's arrivals and departures with the actions a receptionist needs:
/// confirm a pending arrival, check a guest in, or check them out.
class FrontDeskScreen extends StatefulWidget {
  const FrontDeskScreen({super.key});

  @override
  State<FrontDeskScreen> createState() => _FrontDeskScreenState();
}

class _FrontDeskScreenState extends State<FrontDeskScreen> {
  late Future<FrontDeskDay> _future;
  int? _busyBookingId;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<FrontDeskDay> _load() =>
      context.read<HotelRepository>().frontDesk();

  void _reload() => setState(() => _future = _load());

  Future<bool> _confirm(String title, String message, String action) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(action),
          ),
        ],
      ),
    );

    return result == true;
  }

  Future<void> _run(Booking booking, String label, Future<void> Function() task) async {
    setState(() => _busyBookingId = booking.id);

    try {
      await task();
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$label · ${booking.reference}')),
      );
      _reload();
    } on ApiException catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) {
        setState(() => _busyBookingId = null);
      }
    }
  }

  Future<void> _confirmBooking(Booking booking) async {
    final ok = await _confirm(
      'Confirm this arrival?',
      '${booking.guest?.fullName ?? 'The guest'} will be expected today. '
      'They can be checked in once the booking is confirmed.',
      'Confirm',
    );
    if (!ok || !mounted) {
      return;
    }

    await _run(
      booking,
      'Confirmed',
      () => context.read<HotelRepository>().confirmBooking(booking.id),
    );
  }

  Future<void> _checkIn(Booking booking) async {
    final room = booking.rooms.isEmpty ? 'their room' : 'room ${booking.rooms.first.roomNumber}';
    final ok = await _confirm(
      'Check this guest in?',
      '${booking.guest?.fullName ?? 'The guest'} will be checked in to $room.',
      'Check in',
    );
    if (!ok || !mounted) {
      return;
    }

    await _run(
      booking,
      'Checked in',
      () => context.read<HotelRepository>().checkIn(booking.id),
    );
  }

  Future<void> _checkOut(Booking booking) async {
    final stayId = booking.stayId;
    if (stayId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('This booking has no active stay to check out.')),
      );
      return;
    }

    final ok = await _confirm(
      'Check this guest out?',
      '${booking.guest?.fullName ?? 'The guest'} will be checked out and the room sent to housekeeping.',
      'Check out',
    );
    if (!ok || !mounted) {
      return;
    }

    await _run(
      booking,
      'Checked out',
      () => context.read<HotelRepository>().checkOut(stayId),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Front desk'),
        actions: [
          TextButton.icon(
            onPressed: _reload,
            icon: const Icon(Icons.refresh, size: 20),
            label: const Text('Refresh'),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: FutureBuilder<FrontDeskDay>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || !snapshot.hasData) {
            return EmptyState(
              icon: Icons.wifi_off,
              title: 'Could not load the front desk',
              message: '${snapshot.error}',
              onRetry: _reload,
            );
          }

          final day = snapshot.data!;

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
              children: [
                Text(
                  Fmt.prettyDate(DateTime.tryParse(day.date) ?? DateTime.now()),
                  style: const TextStyle(color: AppTheme.muted),
                ),
                const SizedBox(height: 16),
                _SectionHeading(
                  eyebrow: 'ARRIVALS TODAY',
                  count: day.arrivals.length,
                ),
                const SizedBox(height: 10),
                if (day.arrivals.isEmpty)
                  const _Nothing(
                    icon: Icons.login,
                    text: 'No arrivals are due today.',
                  )
                else
                  ...day.arrivals.map(
                    (booking) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _FrontDeskCard(
                        booking: booking,
                        busy: _busyBookingId == booking.id,
                        action: booking.canConfirm
                            ? _FrontDeskAction(
                                label: 'Confirm booking',
                                icon: Icons.check_circle_outline,
                                onPressed: () => _confirmBooking(booking),
                              )
                            : booking.canCheckIn
                                ? _FrontDeskAction(
                                    label: 'Check in',
                                    icon: Icons.login,
                                    onPressed: () => _checkIn(booking),
                                  )
                                : null,
                      ),
                    ),
                  ),
                const SizedBox(height: 12),
                _SectionHeading(
                  eyebrow: 'DEPARTURES TODAY',
                  count: day.departures.length,
                ),
                const SizedBox(height: 10),
                if (day.departures.isEmpty)
                  const _Nothing(
                    icon: Icons.logout,
                    text: 'No guests are due to leave today.',
                  )
                else
                  ...day.departures.map(
                    (booking) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _FrontDeskCard(
                        booking: booking,
                        busy: _busyBookingId == booking.id,
                        action: _FrontDeskAction(
                          label: 'Check out',
                          icon: Icons.logout,
                          onPressed: () => _checkOut(booking),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _FrontDeskAction {
  const _FrontDeskAction({
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final VoidCallback onPressed;
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.eyebrow, required this.count});

  final String eyebrow;
  final int count;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Text(eyebrow, style: AppTheme.eyebrow),
        const SizedBox(width: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 2),
          decoration: BoxDecoration(
            color: AppTheme.charcoal,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            '$count',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }
}

class _Nothing extends StatelessWidget {
  const _Nothing({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: AppTheme.panel(),
      child: Row(
        children: [
          Icon(icon, color: AppTheme.gold),
          const SizedBox(width: 12),
          Expanded(
            child: Text(text, style: const TextStyle(color: AppTheme.muted)),
          ),
        ],
      ),
    );
  }
}

class _FrontDeskCard extends StatelessWidget {
  const _FrontDeskCard({
    required this.booking,
    required this.busy,
    this.action,
  });

  final Booking booking;
  final bool busy;
  final _FrontDeskAction? action;

  @override
  Widget build(BuildContext context) {
    final guest = booking.guest?.fullName ?? 'Guest';

    return Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      guest,
                      style: AppTheme.display(18),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      booking.reference,
                      style: const TextStyle(color: AppTheme.muted, fontSize: 12.5),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              StatusPill(
                label: booking.statusLabel,
                color: StatusPill.colorFor(booking.status),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _Line(
            icon: Icons.calendar_month_outlined,
            text: '${Fmt.prettyDate(booking.checkInDate)} '
                '→ ${Fmt.prettyDate(booking.checkOutDate)}',
          ),
          const SizedBox(height: 6),
          _Line(
            icon: Icons.bed_outlined,
            text: booking.rooms.isEmpty
                ? 'No room assigned'
                : booking.rooms.map((r) => 'Room ${r.roomNumber}').join(', '),
          ),
          if (action != null) ...[
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 50,
              child: FilledButton.icon(
                onPressed: busy ? null : action!.onPressed,
                icon: busy
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Icon(action!.icon, size: 20),
                label: Text(busy ? 'Working…' : action!.label),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _Line extends StatelessWidget {
  const _Line({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 16, color: AppTheme.muted),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(color: AppTheme.muted, fontSize: 13),
          ),
        ),
      ],
    );
  }
}
