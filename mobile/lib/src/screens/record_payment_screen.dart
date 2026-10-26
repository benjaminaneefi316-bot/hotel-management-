import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_exception.dart';
import '../api/hotel_repository.dart';
import '../theme/app_theme.dart';
import '../util/formatters.dart';
import '../widgets/app_widgets.dart';

/// Staff form for recording a payment against a booking.
///
/// No card number, CVV or expiry is ever collected: only the amount, the
/// method and an optional tokenised provider reference, exactly like the web
/// app.
class RecordPaymentScreen extends StatefulWidget {
  const RecordPaymentScreen({
    super.key,
    required this.bookingId,
    required this.reference,
    required this.balance,
  });

  final int bookingId;
  final String reference;
  final double balance;

  @override
  State<RecordPaymentScreen> createState() => _RecordPaymentScreenState();
}

class _RecordPaymentScreenState extends State<RecordPaymentScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _amount;
  final TextEditingController _providerReference = TextEditingController();
  final TextEditingController _notes = TextEditingController();

  String _method = 'cash';
  String _status = 'paid';
  bool _working = false;
  String? _error;

  static const _methods = <String, String>{
    'cash': 'Cash',
    'card': 'Card (tokenised)',
    'bank_transfer': 'Bank transfer',
    'mobile_money': 'Mobile money',
  };

  @override
  void initState() {
    super.initState();
    _amount = TextEditingController(
      text: widget.balance > 0 ? widget.balance.toStringAsFixed(2) : '',
    );
  }

  @override
  void dispose() {
    _amount.dispose();
    _providerReference.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _working = true;
      _error = null;
    });

    try {
      await context.read<HotelRepository>().recordPayment(
            bookingId: widget.bookingId,
            amount: double.parse(_amount.text.trim()),
            method: _method,
            status: _status,
            providerReference: _providerReference.text.trim(),
            notes: _notes.text.trim(),
          );
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
    return Scaffold(
      appBar: AppBar(title: const Text('Record payment')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Panel(
              child: Row(
                children: [
                  const Icon(Icons.event_note, color: AppTheme.gold),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.reference,
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 15,
                          ),
                        ),
                        Text(
                          'Balance ${Fmt.money(widget.balance)}',
                          style: const TextStyle(color: AppTheme.muted),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            if (_error != null) ...[
              Text(_error!, style: const TextStyle(color: AppTheme.danger)),
              const SizedBox(height: 12),
            ],
            AppTextField(
              controller: _amount,
              label: 'Amount',
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              prefixIcon: Icons.attach_money,
              validator: (v) {
                final value = double.tryParse(v?.trim() ?? '');
                if (value == null || value <= 0) {
                  return 'Enter an amount greater than zero.';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _method,
              decoration: const InputDecoration(labelText: 'Method'),
              items: _methods.entries
                  .map(
                    (entry) => DropdownMenuItem(
                      value: entry.key,
                      child: Text(entry.value),
                    ),
                  )
                  .toList(growable: false),
              onChanged: (value) => setState(() => _method = value ?? 'cash'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _status,
              decoration: const InputDecoration(labelText: 'Status'),
              items: const [
                DropdownMenuItem(value: 'paid', child: Text('Paid now')),
                DropdownMenuItem(
                  value: 'pending',
                  child: Text('Promised / pending'),
                ),
              ],
              onChanged: (value) => setState(() => _status = value ?? 'paid'),
            ),
            const SizedBox(height: 12),
            AppTextField(
              controller: _providerReference,
              label: 'Provider reference (optional)',
              hint: 'token_… from the card terminal',
              prefixIcon: Icons.numbers,
            ),
            const SizedBox(height: 12),
            AppTextField(
              controller: _notes,
              label: 'Notes (optional)',
              maxLines: 3,
              prefixIcon: Icons.notes,
            ),
            const SizedBox(height: 18),
            SizedBox(
              height: 52,
              child: FilledButton.icon(
                onPressed: _working ? null : _save,
                icon: const Icon(Icons.check_circle_outline),
                label: Text(_working ? 'Recording…' : 'Record payment'),
              ),
            ),
            const SizedBox(height: 10),
            const Text(
              'Never type a card number, expiry or CVV here. Card payments are '
              'recorded using the reference printed by the terminal.',
              style: TextStyle(color: AppTheme.muted, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}
