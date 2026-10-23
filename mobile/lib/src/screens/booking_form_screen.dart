import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_exception.dart';
import '../api/hotel_repository.dart';
import '../models/booking.dart';
import '../models/room_type.dart';
import '../theme/app_theme.dart';
import '../util/formatters.dart';
import '../widgets/app_widgets.dart';

/// Guest self-service booking: choose dates and party size and the hotel
/// assigns a free room of the chosen class.
class BookingFormScreen extends StatefulWidget {
  const BookingFormScreen({super.key, required this.type});

  final RoomType type;

  @override
  State<BookingFormScreen> createState() => _BookingFormScreenState();
}

class _BookingFormScreenState extends State<BookingFormScreen> {
  late DateTime _checkIn;
  late DateTime _checkOut;
  int _adults = 1;
  int _children = 0;
  final _requests = TextEditingController();

  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final today = DateTime.now();
    _checkIn = DateTime(today.year, today.month, today.day);
    _checkOut = _checkIn.add(const Duration(days: 1));
  }

  @override
  void dispose() {
    _requests.dispose();
    super.dispose();
  }

  int get _nights => _checkOut.difference(_checkIn).inDays;

  String _dateLabel(DateTime date) =>
      '${Fmt.prettyDate(date)}  (${Fmt.isoDate(date)})';

  Future<void> _pick({required bool isCheckIn}) async {
    final today = DateTime.now();
    final firstDate = DateTime(today.year, today.month, today.day);

    final initial = isCheckIn ? _checkIn : _checkOut;

    final picked = await showDatePicker(
      context: context,
      initialDate: initial.isBefore(firstDate) ? firstDate : initial,
      firstDate: firstDate,
      lastDate: firstDate.add(const Duration(days: 730)),
    );

    if (picked == null || !mounted) {
      return;
    }

    setState(() {
      if (isCheckIn) {
        _checkIn = picked;
        // Keep at least one night between the two dates.
        if (!_checkOut.isAfter(_checkIn)) {
          _checkOut = _checkIn.add(const Duration(days: 1));
        }
      } else {
        _checkOut = picked.isAfter(_checkIn)
            ? picked
            : _checkIn.add(const Duration(days: 1));
      }
    });
  }

  Future<void> _submit() async {
    final reason = _requests.text.trim();

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final booking = await context.read<HotelRepository>().bookRoomType(
            roomTypeId: widget.type.id,
            checkInDate: Fmt.isoDate(_checkIn),
            checkOutDate: Fmt.isoDate(_checkOut),
            adults: _adults,
            children: _children,
            specialRequests: reason.isEmpty ? null : reason,
          );

      if (!mounted) {
        return;
      }
      await _showSuccess(booking);
      if (mounted) {
        Navigator.of(context).pop();
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _error = e.message);
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  Future<void> _showSuccess(Booking booking) {
    return showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Reservation received'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Your booking reference is:'),
            const SizedBox(height: 8),
            SelectableText(
              booking.reference,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: AppTheme.charcoal,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'Status: ${booking.statusLabel}',
              style: const TextStyle(color: AppTheme.muted),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Done'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final capacity = widget.type.capacity < 1 ? 1 : widget.type.capacity;

    return Scaffold(
      appBar: AppBar(title: const Text('Check availability')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Panel(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        widget.type.name,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: AppTheme.charcoal,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${Fmt.money(widget.type.basePrice)} per night',
                        style: const TextStyle(color: AppTheme.muted),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.hotel, color: AppTheme.gold, size: 28),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Your stay',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                _DateRow(
                  label: 'Check-in',
                  value: _dateLabel(_checkIn),
                  onTap: () => _pick(isCheckIn: true),
                ),
                const Divider(height: 8),
                _DateRow(
                  label: 'Check-out',
                  value: _dateLabel(_checkOut),
                  onTap: () => _pick(isCheckIn: false),
                ),
                const Divider(height: 8),
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    children: [
                      const Text('Nights', style: TextStyle(fontWeight: FontWeight.w600)),
                      const Spacer(),
                      Text(
                        '${_nights >= 1 ? _nights : 1}',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ],
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
                  'Guests',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                _Counter(
                  label: 'Adults',
                  value: _adults,
                  min: 1,
                  max: capacity,
                  onChanged: (v) => setState(() => _adults = v),
                ),
                const Divider(height: 8),
                _Counter(
                  label: 'Children',
                  value: _children,
                  min: 0,
                  max: 6,
                  onChanged: (v) => setState(() => _children = v),
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
                  'Special requests (optional)',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 10),
                AppTextField(
                  controller: _requests,
                  label: 'Anything we should prepare?',
                  maxLines: 3,
                ),
              ],
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.danger.withAlpha(20),
                borderRadius: BorderRadius.circular(AppTheme.radiusSm),
                border: Border.all(color: AppTheme.danger.withAlpha(80)),
              ),
              child: Text(
                _error!,
                style: const TextStyle(color: AppTheme.danger),
              ),
            ),
          ],
          const SizedBox(height: 20),
          FilledButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('Request reservation'),
          ),
        ],
      ),
    );
  }
}

class _DateRow extends StatelessWidget {
  const _DateRow({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
            const Spacer(),
            Flexible(
              child: Text(
                value,
                textAlign: TextAlign.right,
                style: const TextStyle(color: AppTheme.muted),
              ),
            ),
            const SizedBox(width: 6),
            const Icon(Icons.calendar_today, size: 15, color: AppTheme.gold),
          ],
        ),
      ),
    );
  }
}

class _Counter extends StatelessWidget {
  const _Counter({
    required this.label,
    required this.value,
    required this.min,
    required this.max,
    required this.onChanged,
  });

  final String label;
  final int value;
  final int min;
  final int max;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
          const Spacer(),
          IconButton(
            onPressed: value > min ? () => onChanged(value - 1) : null,
            icon: const Icon(Icons.remove_circle_outline),
            visualDensity: VisualDensity.compact,
          ),
          SizedBox(
            width: 28,
            child: Text(
              '$value',
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
          IconButton(
            onPressed: value < max ? () => onChanged(value + 1) : null,
            icon: const Icon(Icons.add_circle_outline),
            color: AppTheme.charcoal,
          ),
        ],
      ),
    );
  }
}
