import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_exception.dart';
import '../api/hotel_repository.dart';
import '../models/payment.dart';
import '../theme/app_theme.dart';
import '../util/formatters.dart';
import '../widgets/app_widgets.dart';

/// Staff payment ledger: totals, filterable list and refunds.
class PaymentsScreen extends StatefulWidget {
  const PaymentsScreen({super.key});

  @override
  State<PaymentsScreen> createState() => _PaymentsScreenState();
}

class _PaymentsScreenState extends State<PaymentsScreen> {
  final TextEditingController _search = TextEditingController();
  late Future<List<Payment>> _future;
  Future<PaymentSummary>? _summaryFuture;
  String _status = '';
  String? _error;

  @override
  void initState() {
    super.initState();
    _future = _load();
    _summaryFuture = context.read<HotelRepository>().paymentSummary();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<List<Payment>> _load() => context.read<HotelRepository>().payments(
        status: _status,
        search: _search.text.trim(),
      );

  void _reload() => setState(() {
        _error = null;
        _future = _load();
        _summaryFuture = context.read<HotelRepository>().paymentSummary();
      });

  Future<void> _open(Payment payment) async {
    final changed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _PaymentSheet(payment: payment),
    );
    if (changed == true && mounted) {
      _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Payments'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => _reload(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            FutureBuilder<PaymentSummary>(
              future: _summaryFuture,
              builder: (context, snapshot) {
                final summary = snapshot.data;
                return Row(
                  children: [
                    Expanded(
                      child: KpiTile(
                        icon: Icons.savings_outlined,
                        label: 'Collected',
                        value: summary == null
                            ? '—'
                            : Fmt.money(summary.paidTotal),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: KpiTile(
                        icon: Icons.hourglass_bottom,
                        label: 'Awaiting payment',
                        value: summary == null
                            ? '—'
                            : Fmt.money(summary.pendingTotal),
                      ),
                    ),
                  ],
                );
              },
            ),
            const SizedBox(height: 16),
            AppTextField(
              controller: _search,
              label: 'Search booking reference or provider ref',
              prefixIcon: Icons.search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _reload(),
            ),
            const SizedBox(height: 10),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _chip('All', ''),
                  const SizedBox(width: 8),
                  _chip('Paid', 'paid'),
                  const SizedBox(width: 8),
                  _chip('Pending', 'pending'),
                  const SizedBox(width: 8),
                  _chip('Refunded', 'refunded'),
                ],
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 10),
              Text(_error!, style: const TextStyle(color: AppTheme.danger)),
            ],
            const SizedBox(height: 14),
            FutureBuilder<List<Payment>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Padding(
                    padding: EdgeInsets.only(top: 40),
                    child: Center(child: CircularProgressIndicator()),
                  );
                }
                if (snapshot.hasError || !snapshot.hasData) {
                  return EmptyState(
                    icon: Icons.wifi_off,
                    title: 'Could not load payments',
                    message: '${snapshot.error}',
                    onRetry: _reload,
                  );
                }

                final payments = snapshot.data!;
                if (payments.isEmpty) {
                  return const EmptyState(
                    icon: Icons.payments_outlined,
                    title: 'No payments here yet',
                    message:
                        'Record a payment from an open booking, or clear the filter.',
                  );
                }

                return Column(
                  children: payments
                      .map(
                        (payment) => Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: _PaymentCard(
                            payment: payment,
                            onTap: () => _open(payment),
                          ),
                        ),
                      )
                      .toList(growable: false),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _chip(String label, String value) {
    return ChoiceChip(
      label: Text(label),
      selected: _status == value,
      onSelected: (_) {
        setState(() => _status = value);
        _reload();
      },
    );
  }
}

class _PaymentCard extends StatelessWidget {
  const _PaymentCard({required this.payment, required this.onTap});

  final Payment payment;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = switch (payment.status) {
      'paid' => AppTheme.success,
      'pending' => AppTheme.warning,
      'refunded' => AppTheme.muted,
      _ => AppTheme.danger,
    };

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(AppTheme.radius),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppTheme.radius),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: AppTheme.panel(),
          child: Row(
            children: [
              Container(
                height: 42,
                width: 42,
                decoration: BoxDecoration(
                  color: AppTheme.sand,
                  shape: BoxShape.circle,
                  border: Border.all(color: AppTheme.border),
                ),
                child: Icon(Icons.payments_outlined, color: color, size: 21),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      Fmt.money(payment.amount),
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                        color: AppTheme.charcoal,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      [
                        if (payment.bookingReference != null)
                          payment.bookingReference!,
                        if (payment.guestName != null) payment.guestName!,
                        if (payment.methodLabel != null) payment.methodLabel!,
                      ].join(' · '),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppTheme.muted,
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(label: payment.statusLabel, color: color),
            ],
          ),
        ),
      ),
    );
  }
}

class _PaymentSheet extends StatefulWidget {
  const _PaymentSheet({required this.payment});

  final Payment payment;

  @override
  State<_PaymentSheet> createState() => _PaymentSheetState();
}

class _PaymentSheetState extends State<_PaymentSheet> {
  bool _working = false;
  String? _error;

  Future<void> _refund() async {
    final reason = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Refund this payment?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              '${Fmt.money(widget.payment.amount)} will be marked as refunded. '
              'This changes the booking balance.',
            ),
            const SizedBox(height: 12),
            AppTextField(
              controller: reason,
              label: 'Reason (optional)',
              prefixIcon: Icons.notes,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Refund'),
          ),
        ],
      ),
    );
    final reasonText = reason.text.trim();
    reason.dispose();

    if (confirmed != true || !mounted) {
      return;
    }

    setState(() {
      _working = true;
      _error = null;
    });
    try {
      await context
          .read<HotelRepository>()
          .refundPayment(widget.payment.id, reason: reasonText);
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) {
        setState(() => _working = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final payment = widget.payment;
    return Padding(
      padding: EdgeInsets.only(
        left: 18,
        right: 18,
        top: 18,
        bottom: MediaQuery.of(context).viewInsets.bottom + 18,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(Fmt.money(payment.amount), style: AppTheme.display(28)),
          const SizedBox(height: 4),
          Text(
            payment.statusLabel,
            style: const TextStyle(color: AppTheme.muted),
          ),
          const SizedBox(height: 16),
          if (payment.bookingReference != null)
            _Line(label: 'Booking', value: payment.bookingReference!),
          if (payment.guestName != null)
            _Line(label: 'Guest', value: payment.guestName!),
          if (payment.methodLabel != null)
            _Line(label: 'Method', value: payment.methodLabel!),
          if (payment.providerReference != null)
            _Line(label: 'Reference', value: payment.providerReference!),
          if (payment.recordedBy != null)
            _Line(label: 'Recorded by', value: payment.recordedBy!),
          if (payment.paidAt != null)
            _Line(label: 'Paid', value: Fmt.dateTime(payment.paidAt!)),
          if (payment.refundedAt != null)
            _Line(label: 'Refunded', value: Fmt.dateTime(payment.refundedAt!)),
          if (payment.notes != null && payment.notes!.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              payment.notes!,
              style: const TextStyle(color: AppTheme.muted, fontSize: 12.5),
            ),
          ],
          if (_error != null) ...[
            const SizedBox(height: 10),
            Text(_error!, style: const TextStyle(color: AppTheme.danger)),
          ],
          const SizedBox(height: 20),
          if (payment.isPaid)
            SizedBox(
              width: double.infinity,
              height: 50,
              child: FilledButton.icon(
                onPressed: _working ? null : _refund,
                icon: const Icon(Icons.undo),
                label: Text(_working ? 'Refunding…' : 'Refund payment'),
              ),
            )
          else
            SizedBox(
              width: double.infinity,
              height: 50,
              child: OutlinedButton(
                onPressed: () => Navigator.of(context).pop(false),
                child: const Text('Close'),
              ),
            ),
        ],
      ),
    );
  }
}

class _Line extends StatelessWidget {
  const _Line({required this.label, required this.value});

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
