import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../api/api_exception.dart';
import '../api/hotel_repository.dart';
import '../models/two_factor.dart';
import '../theme/app_theme.dart';
import '../widgets/app_widgets.dart';

/// Authenticator-app security settings: enrol, or remove, a TOTP app.
///
/// The screen is deliberately three clear steps, because a half-finished setup
/// must never lock anybody out. Nothing is saved until a live 6-digit code from
/// the app has been verified.
class SecurityScreen extends StatefulWidget {
  const SecurityScreen({super.key});

  @override
  State<SecurityScreen> createState() => _SecurityScreenState();
}

class _SecurityScreenState extends State<SecurityScreen> {
  final TextEditingController _code = TextEditingController();
  final TextEditingController _disableCode = TextEditingController();

  late Future<TwoFactorStatus> _status;
  TwoFactorSetup? _setup;
  bool _working = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _status = context.read<HotelRepository>().twoFactorStatus();
  }

  @override
  void dispose() {
    _code.dispose();
    _disableCode.dispose();
    super.dispose();
  }

  void _reload() => setState(() {
        _setup = null;
        _error = null;
        _code.clear();
        _status = context.read<HotelRepository>().twoFactorStatus();
      });

  Future<void> _startSetup() async {
    setState(() {
      _working = true;
      _error = null;
    });
    try {
      final setup = await context.read<HotelRepository>().startTwoFactorSetup();
      setState(() => _setup = setup);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) {
        setState(() => _working = false);
      }
    }
  }

  Future<void> _copySecret(String secret) async {
    await Clipboard.setData(ClipboardData(text: secret));
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Setup key copied. Paste it into your authenticator app.'),
        ),
      );
    }
  }

  Future<void> _confirmSetup() async {
    final code = _code.text.trim();
    if (!RegExp(r'^\d{6}$').hasMatch(code)) {
      setState(() => _error = 'Enter the 6-digit code shown in your app.');
      return;
    }

    setState(() {
      _working = true;
      _error = null;
    });
    try {
      await context.read<HotelRepository>().confirmTwoFactor(code);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Authenticator connected. Sign-in now asks for its code.'),
          ),
        );
      }
      _reload();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) {
        setState(() => _working = false);
      }
    }
  }

  Future<void> _turnOff() async {
    final code = _disableCode.text.trim();
    if (!RegExp(r'^\d{6}$').hasMatch(code)) {
      setState(() => _error = 'Enter the current 6-digit code from your app.');
      return;
    }

    setState(() {
      _working = true;
      _error = null;
    });
    try {
      await context.read<HotelRepository>().disableTwoFactor(code);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Authenticator removed. Sign-in will no longer ask for a code.'),
          ),
        );
      }
      _reload();
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
      appBar: AppBar(
        title: const Text('Security'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<TwoFactorStatus>(
        future: _status,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || !snapshot.hasData) {
            return EmptyState(
              icon: Icons.wifi_off,
              title: 'Could not load your security settings',
              message: '${snapshot.error}',
              onRetry: _reload,
            );
          }

          final status = snapshot.data!;

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Panel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(
                          status.enabled
                              ? Icons.verified_user
                              : Icons.gpp_maybe_outlined,
                          color: status.enabled ? AppTheme.success : AppTheme.gold,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            status.enabled
                                ? 'Authenticator app connected'
                                : 'Extra protection is off',
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              color: AppTheme.charcoal,
                            ),
                          ),
                        ),
                        StatusPill(
                          label: status.enabled ? 'On' : 'Off',
                          color:
                              status.enabled ? AppTheme.success : AppTheme.warning,
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(
                      status.enabled
                          ? 'When you sign in, the app asks for the 6-digit code '
                              'shown in your authenticator app. No email needed.'
                          : 'Add Google Authenticator (or Authy / 1Password) so a '
                              'stolen password alone cannot open this account.',
                      style: const TextStyle(color: AppTheme.muted, height: 1.4),
                    ),
                  ],
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 14),
                _ErrorBox(message: _error!),
              ],
              const SizedBox(height: 18),
              if (!status.enabled && _setup == null) ...[
                SizedBox(
                  height: 54,
                  child: FilledButton.icon(
                    onPressed: _working ? null : _startSetup,
                    icon: const Icon(Icons.qr_code_2),
                    label: Text(
                      _working ? 'Preparing…' : 'Connect an authenticator app',
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                const Text(
                  'You will be given a setup key to paste into the app, then '
                  'asked for one code to confirm it works.',
                  style: TextStyle(color: AppTheme.muted, fontSize: 12.5),
                ),
              ],
              if (!status.enabled && _setup != null)
                _SetupPanel(
                  setup: _setup!,
                  controller: _code,
                  working: _working,
                  onCopy: () => _copySecret(_setup!.secret),
                  onConfirm: _confirmSetup,
                  onCancel: _reload,
                ),
              if (status.enabled)
                _DisablePanel(
                  controller: _disableCode,
                  working: _working,
                  onTurnOff: _turnOff,
                ),
            ],
          );
        },
      ),
    );
  }
}

class _SetupPanel extends StatelessWidget {
  const _SetupPanel({
    required this.setup,
    required this.controller,
    required this.working,
    required this.onCopy,
    required this.onConfirm,
    required this.onCancel,
  });

  final TwoFactorSetup setup;
  final TextEditingController controller;
  final bool working;
  final VoidCallback onCopy;
  final VoidCallback onConfirm;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    return Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('STEP 1 · ADD THE ACCOUNT', style: AppTheme.eyebrow),
          const SizedBox(height: 10),
          const Text(
            'Open your authenticator app, tap +, choose "Enter a setup key", '
            'then paste the key below. Keep "Time based" selected.',
            style: TextStyle(color: AppTheme.text, height: 1.45),
          ),
          const SizedBox(height: 14),
          _FieldLabel(label: 'Account', value: setup.account),
          const SizedBox(height: 8),
          _FieldLabel(label: 'Issuer', value: setup.issuer),
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppTheme.sand,
              borderRadius: BorderRadius.circular(AppTheme.radiusSm),
              border: Border.all(color: AppTheme.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'SETUP KEY',
                  style: TextStyle(
                    fontSize: 10,
                    letterSpacing: 1.4,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.muted,
                  ),
                ),
                const SizedBox(height: 6),
                SelectableText(
                  setup.groupedSecret,
                  style: const TextStyle(
                    fontFamily: 'monospace',
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.2,
                    color: AppTheme.charcoal,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: OutlinedButton.icon(
              onPressed: onCopy,
              icon: const Icon(Icons.copy_all_outlined, size: 19),
              label: const Text('Copy setup key'),
            ),
          ),
          const SizedBox(height: 22),
          const Text('STEP 2 · CONFIRM', style: AppTheme.eyebrow),
          const SizedBox(height: 10),
          AppTextField(
            controller: controller,
            label: '6-digit code',
            hint: '123456',
            keyboardType: TextInputType.number,
            prefixIcon: Icons.pin_outlined,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => onConfirm(),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: working ? null : onCancel,
                  child: const Text('Cancel'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: FilledButton(
                  onPressed: working ? null : onConfirm,
                  child: Text(working ? 'Checking…' : 'Turn on 2FA'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DisablePanel extends StatelessWidget {
  const _DisablePanel({
    required this.controller,
    required this.working,
    required this.onTurnOff,
  });

  final TextEditingController controller;
  final bool working;
  final VoidCallback onTurnOff;

  @override
  Widget build(BuildContext context) {
    return Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('TURN IT OFF', style: AppTheme.eyebrow),
          const SizedBox(height: 10),
          const Text(
            'Enter a current code to remove the authenticator. Sign-in will '
            'then use your password only. If you have lost the app, ask another '
            'administrator for help before removing it.',
            style: TextStyle(color: AppTheme.muted, height: 1.45),
          ),
          const SizedBox(height: 14),
          AppTextField(
            controller: controller,
            label: 'Current 6-digit code',
            hint: '123456',
            keyboardType: TextInputType.number,
            prefixIcon: Icons.pin_outlined,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => onTurnOff(),
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: OutlinedButton.icon(
              onPressed: working ? null : onTurnOff,
              icon: const Icon(Icons.link_off, size: 19),
              label: Text(working ? 'Removing…' : 'Remove authenticator'),
            ),
          ),
        ],
      ),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Text(
          label,
          style: const TextStyle(color: AppTheme.muted, fontSize: 12.5),
        ),
        const Spacer(),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(
              fontWeight: FontWeight.w600,
              color: AppTheme.charcoal,
            ),
          ),
        ),
      ],
    );
  }
}

class _ErrorBox extends StatelessWidget {
  const _ErrorBox({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppTheme.danger.withAlpha(18),
        borderRadius: BorderRadius.circular(AppTheme.radiusSm),
        border: Border.all(color: AppTheme.danger.withAlpha(70)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: AppTheme.danger, size: 20),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: AppTheme.danger),
            ),
          ),
        ],
      ),
    );
  }
}
