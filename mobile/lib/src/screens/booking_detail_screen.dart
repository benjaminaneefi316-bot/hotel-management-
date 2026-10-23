import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_exception.dart';
import '../api/hotel_repository.dart';
import '../models/booking.dart';
import '../theme/app_theme.dart';
import '../util/formatters.dart';
import '../widgets/app_widgets.dart';

/// Full detail for one reservation, with a cancel action when permitted.
class BookingDetailScreen extends StatefulWidget {
  const BookingDetailScreen({super.key, required this.bookingId});

  final int bookingId;

  @override
  State<BookingDetailScreen> createState() => _BookingDetailScreenState();
}

class _BookingDetailScreenState extends State<BookingDetailScreen> {
  late Future<Booking> _future;
  bool _working = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Booking> _load() =>
      context.read<HotelRepository>().booking(widget.bookingId);

  void _reload() => setState(() => _future = _load());

  Future<void> _cancel(Booking booking) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel booking?'),
        content: Text(
          'This will release the rooms on ${booking.reference}. '
          'This cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Keep booking'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Cancel booking'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) {
      return;
    }

    setState(() {
      _working = true;
      _error = null;
    });

    try {
      await context.read<HotelRepository>().cancelBooking(widget.bookingId);
      if (mounted) {
        _reload();
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _error = e.message);
      }
    } finally {
      if (mounted) {
        setState(() => _working = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Booking')),
      body: FutureBuilder<Booking>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || !snapshot.hasData) {
            return EmptyState(
              icon: Icons.error_outline,
              title: 'Could not load this booking',
              message: '${snapshot.error}',
              onRetry: _reload,
            );
          }

          final booking = snapshot.data!;
          final color = StatusPill.colorFor(booking.status);

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Panel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            booking.reference,
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w700,
                              color: AppTheme.charcoal,
                            ),
                          ),
                        ),
                        StatusPill(label: booking.statusLabel, color: color),
                      ],
                    ),
                    const SizedBox(height: 12),
                    _KV('Check-in', Fmt.prettyDate(booking.checkInDate)),
                    _KV('Check-out', Fmt.prettyDate(booking.checkOutDate)),
                    _KV('Nights', '${booking.nights}'),
                    _KV(
                      'Guests',
                      '${booking.adults} adult(s), ${booking.children} child(ren)',
                    ),
                  ],
                ),
              ),
              if (booking.guest != null) ...[
                const SizedBox(height: 16),
                Panel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Guest',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 8),
                      _KV('Name', booking.guest!.fullName),
                      if (booking.guest!.email != null)
                        _KV('Email', booking.guest!.email!),
                      if (booking.guest!.phone != null)
                        _KV('Phone', booking.guest!.phone!),
                      if (booking.guest!.country != null)
                        _KV('Country', booking.guest!.country!),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 16),
              Panel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Rooms',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 8),
                    if (booking.rooms.isEmpty)
                      const Text(
                        'The hotel will assign a room before arrival.',
                        style: TextStyle(color: AppTheme.muted),
                      )
                    else
                      ...booking.rooms.map(
                        (room) => _KV(
                          'Room ${room.roomNumber}',
                          Fmt.money(room.rate),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Panel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Payments',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 8),
                    if (booking.payments.isEmpty)
                      const Text(
                        'No payments recorded yet.',
                        style: TextStyle(color: AppTheme.muted),
                      )
                    else
                      ...booking.payments.map(
                        (p) => _KV(
                          '${p.statusLabel}'
                          '${p.methodLabel != null ? ' Â· ${p.methodLabel}' : ''}',
                          Fmt.money(p.amount, currency: p.currency),
                        ),
                      ),
                    const Divider(height: 20),
                    _KV('Total', Fmt.money(booking.totalAmount)),
                    _KV('Paid', Fmt.money(booking.paidAmount)),
                    _KV('Balance', Fmt.money(booking.balance)),
                  ],
                ),
              ),
              if (booking.specialRequests != null &&
                  booking.specialRequests!.trim().isNotEmpty) ...[
                const SizedBox(height: 16),
                Panel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Special requests',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 6),
                      Text(booking.specialRequests!),
                    ],
                  ),
                ),
              ],
              if (_error != null) ...[
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.danger.withAlpha(20),
                    borderRadius: BorderRadius.circular(AppTheme.radiusSm),
                  ),
                  child: Text(
                    _error!,
                    style: const TextStyle(color: AppTheme.danger),
                  ),
                ),
              ],
              if (booking.canCancel) ...[
                const SizedBox(height: 20),
                OutlinedButton.icon(
                  onPressed: _working ? null : () => _cancel(booking),
                  icon: const Icon(Icons.cancel_outlined, size: 18),
                  label: Text(_working ? 'Cancellingâ€¦' : 'Cancel booking'),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _KV extends StatelessWidget {
  const _KV(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(color: AppTheme.muted, fontSize: 13),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
